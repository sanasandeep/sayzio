<?php

namespace Tests\Feature;

use App\Jobs\ProcessAdminAssetZipImportJob;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminAsset;
use App\Modules\Admin\Models\AdminAssetFolder;
use App\Modules\Admin\Models\AdminAssetImport;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Regression coverage for the Asset Vault zip import
 * (ProcessAdminAssetZipImportJob + AdminAssetController::importZip()).
 *
 * Security-sensitive behaviors locked down here:
 *   - zip-slip / unsafe paths ("../evil.png", absolute paths) are skipped
 *   - OS junk (.DS_Store, __MACOSX, ._resource forks) is silently ignored
 *   - non-image and oversized (> 30 MB) entries are skipped with reasons
 *   - accepted images land under admin-assets/images/{folder}/ and the
 *     archive's top-level folders are mirrored as vault folders
 *   - skip-mode re-import is idempotent (everything skipped), overwrite
 *     mode overwrites in place
 *   - the uploaded temp zip is always deleted afterwards
 *   - controller: only one active import at a time (422), both routes are
 *     admin-guarded
 */
/**
 * Testable variant of the job: a tiny 1 KB download cap (so the streaming
 * size guard can be exercised without gigabytes) and a single allow-listed
 * loopback origin so a local test HTTP server can play the "public" first
 * hop. Every OTHER url — including redirect targets — still goes through
 * the real assertSafeHttpUrl guard.
 */
class TestableZipImportJob extends ProcessAdminAssetZipImportJob
{
    public const MAX_ZIP_BYTES = 1024; // 1 KB cap for streaming-guard tests

    public static string $allowedOrigin = '';

    protected function assertSafeHttpUrl(string $url): void
    {
        if (static::$allowedOrigin !== '' && str_starts_with($url, static::$allowedOrigin)) {
            return; // pretend this test origin is a safe public host
        }
        parent::assertSafeHttpUrl($url);
    }
}

class AdminAssetZipImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var resource|null */
    private static $httpServer = null;
    private static string $httpOrigin = '';

    public static function tearDownAfterClass(): void
    {
        if (self::$httpServer) {
            proc_terminate(self::$httpServer);
            proc_close(self::$httpServer);
            self::$httpServer = null;
        }
        parent::tearDownAfterClass();
    }

    /** Start (once) a local HTTP server with redirect/big-file routes. */
    private function httpOrigin(): string
    {
        if (self::$httpOrigin !== '') {
            return self::$httpOrigin;
        }
        $router = sys_get_temp_dir() . '/zipimport-test-router.php';
        file_put_contents($router, <<<'PHP'
<?php
$uri = $_SERVER['REQUEST_URI'];
if (str_starts_with($uri, '/to-private')) {
    header('Location: http://10.0.0.5/evil.zip', true, 302);
    exit;
}
if (preg_match('#^/loop/(\d+)#', $uri, $m)) {
    header('Location: /loop/' . ((int) $m[1] + 1), true, 302);
    exit;
}
if (str_starts_with($uri, '/big.zip')) {
    header('Content-Type: application/zip');
    echo str_repeat('A', 4096); // 4 KB > the 1 KB test cap
    exit;
}
http_response_code(404);
PHP);

        $port = 0;
        for ($try = 0; $try < 10; $try++) {
            $candidate = random_int(49200, 64000);
            $proc = proc_open(
                ['php', '-S', '127.0.0.1:' . $candidate, $router],
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes
            );
            if (!is_resource($proc)) {
                continue;
            }
            // Poll until the server accepts connections.
            for ($i = 0; $i < 50; $i++) {
                $sock = @fsockopen('127.0.0.1', $candidate, $ec, $em, 0.1);
                if ($sock) {
                    fclose($sock);
                    self::$httpServer = $proc;
                    $port = $candidate;
                    break 2;
                }
                usleep(100_000);
            }
            proc_terminate($proc);
            proc_close($proc);
        }
        $this->assertGreaterThan(0, $port, 'Could not start the local test HTTP server');

        return self::$httpOrigin = 'http://127.0.0.1:' . $port;
    }

    private function runUrlImport(string $source): AdminAssetImport
    {
        $import = AdminAssetImport::create([
            'admin_id'    => $this->makeAdmin()->id,
            'status'      => 'pending',
            'source_type' => 'url',
            'source'      => $source,
            'mode'        => 'skip',
        ]);
        (new TestableZipImportJob($import->id))->handle();

        return $import->fresh();
    }

    /** 1x1 transparent PNG — passes the getimagesize() content sniff. */
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk'
            . 'YPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        );
    }

    private function fakeLocalDisk(): void
    {
        $disk = AdminAsset::diskName();
        Storage::fake($disk);
        config(["filesystems.disks.{$disk}.driver" => 'local']);
    }

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Zip Admin',
            'email'    => 'zipadmin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /**
     * Build the fixture archive on disk and return its path. Contents:
     *   avatars/one.png          — valid image inside a folder
     *   top.png                  — valid image at archive root
     *   ../evil.png              — zip-slip traversal (must be skipped)
     *   /abs.png                 — absolute path (must be skipped)
     *   avatars/notes.txt        — non-image (skipped with reason)
     *   avatars/huge.png         — > 30 MB (skipped with reason)
     *   avatars/empty.png        — zero bytes (skipped with reason)
     *   .DS_Store, __MACOSX/..., ._one.png — OS junk (silently ignored)
     */
    private function buildFixtureZip(): string
    {
        $dir = storage_path('app/asset-imports');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/test-import-' . uniqid() . '.zip';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $png = $this->pngBytes();
        $zip->addFromString('avatars/one.png', $png);
        $zip->addFromString('top.png', $png);
        $zip->addFromString('../evil.png', $png);
        $zip->addFromString('/abs.png', $png);
        $zip->addFromString('avatars/notes.txt', 'not an image');
        $zip->addFromString('avatars/huge.png', str_repeat("\0", ProcessAdminAssetZipImportJob::MAX_ENTRY_BYTES + 1));
        $zip->addFromString('avatars/empty.png', '');
        $zip->addFromString('.DS_Store', 'junk');
        $zip->addFromString('__MACOSX/avatars/._one.png', 'junk');
        $zip->addFromString('avatars/._one.png', 'junk');
        $zip->close();

        return $path;
    }

    private function runImport(string $zipPath, string $mode = 'skip'): AdminAssetImport
    {
        $import = AdminAssetImport::create([
            'admin_id'    => $this->makeAdmin()->id,
            'status'      => 'pending',
            'source_type' => 'upload',
            'source'      => 'fixture.zip',
            'mode'        => $mode,
            'zip_path'    => $zipPath,
        ]);

        (new ProcessAdminAssetZipImportJob($import->id))->handle();

        return $import->fresh();
    }

    private function skippedReasons(AdminAssetImport $import): array
    {
        $out = [];
        foreach ((array) ($import->skipped ?? []) as $row) {
            $out[$row['path']] = $row['reason'];
        }
        return $out;
    }

    /* ───────────────────────── job behavior ───────────────────────── */

    public function test_import_files_images_and_skips_unsafe_and_invalid_entries(): void
    {
        $this->fakeLocalDisk();
        $zipPath = $this->buildFixtureZip();

        $import = $this->runImport($zipPath);

        $this->assertSame('completed', $import->status);
        $this->assertSame(2, $import->imported_count);
        $this->assertSame(0, $import->overwritten_count);

        // Images filed under admin-assets/images/{folder}/, deterministic names.
        $disk = AdminAsset::diskName();
        $folderPath = 'admin-assets/images/avatars/' . sha1('avatars/one.png') . '.png';
        $rootPath   = 'admin-assets/images/imported/' . sha1('top.png') . '.png';
        Storage::disk($disk)->assertExists($folderPath);
        Storage::disk($disk)->assertExists($rootPath);
        $this->assertDatabaseHas('admin_assets', ['path' => $folderPath, 'folder' => 'avatars', 'type' => 'image']);
        $this->assertDatabaseHas('admin_assets', ['path' => $rootPath, 'folder' => null]);

        // The archive's top-level folder was mirrored as a vault folder.
        $this->assertTrue(AdminAssetFolder::where('slug', 'avatars')->exists());

        // Skips carry their reasons; zip-slip and absolute paths are rejected.
        $reasons = $this->skippedReasons($import);
        $this->assertSame('Unsafe path', $reasons['../evil.png'] ?? null);
        $this->assertSame('Unsafe path', $reasons['/abs.png'] ?? null);
        $this->assertSame('Not a supported image type', $reasons['avatars/notes.txt'] ?? null);
        $this->assertSame('Exceeds the 30 MB per-image limit', $reasons['avatars/huge.png'] ?? null);
        $this->assertSame('Empty file', $reasons['avatars/empty.png'] ?? null);
        $this->assertSame(5, $import->skipped_count);

        // Nothing escaped the images prefix, and no traversal artifacts exist.
        foreach (AdminAsset::all() as $asset) {
            $this->assertStringStartsWith('admin-assets/images/', $asset->path);
            $this->assertStringNotContainsString('..', $asset->path);
        }

        // OS junk is ignored silently — never imported, never in the skip list.
        foreach (array_keys($reasons) as $skippedPath) {
            $this->assertStringNotContainsString('.DS_Store', $skippedPath);
            $this->assertStringNotContainsString('__MACOSX', $skippedPath);
            $this->assertStringNotContainsString('._one.png', $skippedPath);
        }

        // Temp zip cleaned up and forgotten on the row.
        $this->assertFileDoesNotExist($zipPath);
        $this->assertNull($import->zip_path);
    }

    public function test_skip_mode_reimport_skips_everything(): void
    {
        $this->fakeLocalDisk();
        $this->runImport($this->buildFixtureZip());

        // The job deletes the archive, so rebuild an identical one.
        $second = $this->runImport($this->buildFixtureZip(), 'skip');

        $this->assertSame('completed', $second->status);
        $this->assertSame(0, $second->imported_count);
        $this->assertSame(0, $second->overwritten_count);

        $reasons = $this->skippedReasons($second);
        $this->assertSame('Already imported (skipped)', $reasons['avatars/one.png'] ?? null);
        $this->assertSame('Already imported (skipped)', $reasons['top.png'] ?? null);

        // No duplicate rows were minted.
        $this->assertSame(2, AdminAsset::count());
    }

    public function test_overwrite_mode_overwrites_existing_assets(): void
    {
        $this->fakeLocalDisk();
        $this->runImport($this->buildFixtureZip());

        $second = $this->runImport($this->buildFixtureZip(), 'overwrite');

        $this->assertSame('completed', $second->status);
        $this->assertSame(0, $second->imported_count);
        $this->assertSame(2, $second->overwritten_count);
        $this->assertSame(2, AdminAsset::count());

        $reasons = $this->skippedReasons($second);
        $this->assertArrayNotHasKey('avatars/one.png', $reasons);
        $this->assertArrayNotHasKey('top.png', $reasons);
    }

    /* ───────────────────────── remote fetch guards ───────────────────────── */

    /** Invoke a private method on the job via reflection. */
    private function invokeJob(string $method, mixed ...$args): mixed
    {
        $job = new ProcessAdminAssetZipImportJob(0);
        $ref = new \ReflectionMethod($job, $method);
        return $ref->invoke($job, ...$args);
    }

    /** Temp files matching the download prefix (for leak assertions). */
    private function downloadTempFiles(): array
    {
        return glob(sys_get_temp_dir() . '/vaultzipdl_*') ?: [];
    }

    public function test_assert_safe_http_url_rejects_non_http_schemes(): void
    {
        foreach (['ftp://example.com/a.zip', 'file:///etc/passwd', 'gopher://example.com/x', 'not-a-url', ''] as $url) {
            try {
                $this->invokeJob('assertSafeHttpUrl', $url);
                $this->fail('Expected rejection for: ' . $url);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Only http(s) URLs', $e->getMessage(), $url);
            }
        }
    }

    public function test_assert_safe_http_url_rejects_private_and_loopback_ips(): void
    {
        $private = [
            'http://127.0.0.1/a.zip',      // loopback
            'https://10.0.0.5/a.zip',      // RFC1918
            'http://192.168.1.10/a.zip',   // RFC1918
            'http://172.16.3.4/a.zip',     // RFC1918
            'http://169.254.169.254/meta', // link-local (cloud metadata)
            'http://0.0.0.0/a.zip',        // reserved
        ];
        foreach ($private as $url) {
            try {
                $this->invokeJob('assertSafeHttpUrl', $url);
                $this->fail('Expected rejection for: ' . $url);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('private/internal address', $e->getMessage(), $url);
            }
        }
    }

    public function test_assert_safe_http_url_rejects_unresolvable_hosts(): void
    {
        // .invalid is reserved (RFC 2606) and never resolves.
        try {
            $this->invokeJob('assertSafeHttpUrl', 'https://archive.download.invalid/a.zip');
            $this->fail('Expected rejection for unresolvable host');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Could not resolve', $e->getMessage());
        }
    }

    public function test_assert_safe_http_url_allows_public_addresses(): void
    {
        // Public IP literal — no DNS dependency, must pass the guard.
        $this->invokeJob('assertSafeHttpUrl', 'https://93.184.216.34/archive.zip');
        $this->assertTrue(true); // no exception thrown
    }

    public function test_download_from_s3_rejects_malformed_locations(): void
    {
        config(['filesystems.disks.s3.bucket' => 'sayzio-assets']);
        $dest = tempnam(sys_get_temp_dir(), 'vaultziptest_');

        try {
            foreach (['s3://', 's3://bucket-only', 's3:///no-bucket/key.zip'] as $source) {
                try {
                    $this->invokeJob('downloadFromS3', $source, $dest);
                    $this->fail('Expected rejection for: ' . $source);
                } catch (\RuntimeException $e) {
                    $this->assertStringContainsString('s3://bucket/path', $e->getMessage(), $source);
                }
            }
        } finally {
            @unlink($dest);
        }
    }

    public function test_download_from_s3_rejects_non_configured_bucket(): void
    {
        config(['filesystems.disks.s3.bucket' => 'sayzio-assets']);
        $dest = tempnam(sys_get_temp_dir(), 'vaultziptest_');

        try {
            try {
                $this->invokeJob('downloadFromS3', 's3://attacker-bucket/archive.zip', $dest);
                $this->fail('Expected rejection for foreign bucket');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Only the configured S3 bucket', $e->getMessage());
                $this->assertStringContainsString('sayzio-assets', $e->getMessage());
            }

            // With no bucket configured at all, everything is rejected.
            config(['filesystems.disks.s3.bucket' => '']);
            try {
                $this->invokeJob('downloadFromS3', 's3://sayzio-assets/archive.zip', $dest);
                $this->fail('Expected rejection when no bucket is configured');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('Only the configured S3 bucket', $e->getMessage());
            }
        } finally {
            @unlink($dest);
        }
    }

    public function test_failed_url_import_marks_row_failed_and_leaves_no_temp_files(): void
    {
        $this->fakeLocalDisk();
        $before = $this->downloadTempFiles();

        // Loopback URL: rejected by the SSRF guard before any network I/O.
        $import = AdminAssetImport::create([
            'admin_id'    => $this->makeAdmin()->id,
            'status'      => 'pending',
            'source_type' => 'url',
            'source'      => 'http://127.0.0.1/archive.zip',
            'mode'        => 'skip',
        ]);

        (new ProcessAdminAssetZipImportJob($import->id))->handle();
        $import->refresh();

        $this->assertSame('failed', $import->status);
        $this->assertNotNull($import->completed_at);
        $this->assertStringContainsString('private/internal address', (string) $import->error);
        $this->assertSame(0, AdminAsset::count());

        // The download temp file was cleaned up.
        $this->assertSame($before, $this->downloadTempFiles());
    }

    public function test_failed_scheme_url_import_records_error_message(): void
    {
        $this->fakeLocalDisk();
        $before = $this->downloadTempFiles();

        $import = AdminAssetImport::create([
            'admin_id'    => $this->makeAdmin()->id,
            'status'      => 'pending',
            'source_type' => 'url',
            'source'      => 'ftp://example.com/archive.zip',
            'mode'        => 'skip',
        ]);

        (new ProcessAdminAssetZipImportJob($import->id))->handle();
        $import->refresh();

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Only http(s) URLs', (string) $import->error);
        $this->assertSame($before, $this->downloadTempFiles());
    }

    public function test_failed_s3_import_marks_row_failed_without_temp_leak(): void
    {
        $this->fakeLocalDisk();
        config(['filesystems.disks.s3.bucket' => 'sayzio-assets']);
        $before = $this->downloadTempFiles();

        $import = AdminAssetImport::create([
            'admin_id'    => $this->makeAdmin()->id,
            'status'      => 'pending',
            'source_type' => 'url',
            'source'      => 's3://someone-elses-bucket/archive.zip',
            'mode'        => 'skip',
        ]);

        (new ProcessAdminAssetZipImportJob($import->id))->handle();
        $import->refresh();

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Only the configured S3 bucket', (string) $import->error);
        $this->assertSame(0, AdminAsset::count());
        $this->assertSame($before, $this->downloadTempFiles());
    }

    /* ─────────────── redirect hops & streaming size cap ─────────────── */

    public function test_redirect_hop_to_private_address_is_rejected(): void
    {
        $this->fakeLocalDisk();
        $origin = $this->httpOrigin();
        TestableZipImportJob::$allowedOrigin = $origin;
        $before = $this->downloadTempFiles();

        try {
            // First hop is the allow-listed test origin; it 302s to a
            // private address, which the guard must reject at hop 2.
            $import = $this->runUrlImport($origin . '/to-private');
        } finally {
            TestableZipImportJob::$allowedOrigin = '';
        }

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('private/internal address', (string) $import->error);
        $this->assertSame(0, AdminAsset::count());
        $this->assertSame($before, $this->downloadTempFiles());
    }

    public function test_redirect_loop_hits_the_hop_cap(): void
    {
        $this->fakeLocalDisk();
        $origin = $this->httpOrigin();
        TestableZipImportJob::$allowedOrigin = $origin;
        $before = $this->downloadTempFiles();

        try {
            $import = $this->runUrlImport($origin . '/loop/1');
        } finally {
            TestableZipImportJob::$allowedOrigin = '';
        }

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('Too many redirects', (string) $import->error);
        $this->assertSame($before, $this->downloadTempFiles());
    }

    public function test_http_download_exceeding_size_cap_fails_and_cleans_up(): void
    {
        $this->fakeLocalDisk();
        $origin = $this->httpOrigin();
        TestableZipImportJob::$allowedOrigin = $origin;
        $before = $this->downloadTempFiles();

        try {
            // The server streams 4 KB; the testable job caps at 1 KB, so
            // the transfer must be aborted mid-stream (partial write).
            $import = $this->runUrlImport($origin . '/big.zip');
        } finally {
            TestableZipImportJob::$allowedOrigin = '';
        }

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('import limit', (string) $import->error);
        $this->assertSame(0, AdminAsset::count());
        // Even after a partial write, the download temp file is removed.
        $this->assertSame($before, $this->downloadTempFiles());
    }

    public function test_s3_download_exceeding_size_cap_fails_and_cleans_up(): void
    {
        $this->fakeLocalDisk();
        Storage::fake('s3');
        config(['filesystems.disks.s3.bucket' => 'sayzio-assets']);
        // 4 KB object vs the 1 KB test cap — aborted while streaming.
        Storage::disk('s3')->put('archive.zip', str_repeat('B', 4096));
        $before = $this->downloadTempFiles();

        $import = $this->runUrlImport('s3://sayzio-assets/archive.zip');

        $this->assertSame('failed', $import->status);
        $this->assertStringContainsString('import limit', (string) $import->error);
        $this->assertSame(0, AdminAsset::count());
        $this->assertSame($before, $this->downloadTempFiles());
    }

    /* ───────────────────────── controller guards ───────────────────────── */

    public function test_only_one_active_import_at_a_time(): void
    {
        Queue::fake();
        AdminAssetImport::create([
            'status'      => 'processing',
            'source_type' => 'upload',
            'source'      => 'running.zip',
            'mode'        => 'skip',
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.assets.import-zip'), [
                'file' => UploadedFile::fake()->create('more.zip', 10, 'application/zip'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Queue::assertNotPushed(ProcessAdminAssetZipImportJob::class);
        $this->assertSame(1, AdminAssetImport::count());
    }

    /* ───────────────── stale-import reaping & cancel ───────────────── */

    public function test_stale_import_is_reaped_and_no_longer_blocks_new_imports(): void
    {
        Queue::fake();

        // A worker died mid-run: the row has sat "processing" with no
        // progress for longer than the job's timeout window.
        $stale = AdminAssetImport::create([
            'status'      => 'processing',
            'source_type' => 'upload',
            'source'      => 'dead.zip',
            'mode'        => 'skip',
            'started_at'  => now()->subHours(3),
        ]);
        AdminAssetImport::whereKey($stale->id)->update([
            'updated_at' => now()->subMinutes(AdminAssetImport::STALE_AFTER_MINUTES + 5),
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.assets.import-zip'), [
                'file' => UploadedFile::fake()->create('fresh.zip', 10, 'application/zip'),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Queue::assertPushed(ProcessAdminAssetZipImportJob::class);

        $stale->refresh();
        $this->assertSame('failed', $stale->status);
        $this->assertNotNull($stale->completed_at);
        $this->assertStringContainsString('stalled', $stale->error);
    }

    public function test_recent_active_import_is_not_reaped(): void
    {
        Queue::fake();
        AdminAssetImport::create([
            'status'      => 'processing',
            'source_type' => 'upload',
            'source'      => 'running.zip',
            'mode'        => 'skip',
            'started_at'  => now()->subMinutes(5),
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.assets.import-zip'), [
                'file' => UploadedFile::fake()->create('more.zip', 10, 'application/zip'),
            ])
            ->assertStatus(422);

        Queue::assertNotPushed(ProcessAdminAssetZipImportJob::class);
        $this->assertSame('processing', AdminAssetImport::first()->status);
    }

    public function test_imports_poll_endpoint_reaps_stale_rows(): void
    {
        $stale = AdminAssetImport::create([
            'status'      => 'downloading',
            'source_type' => 'url',
            'source'      => 'https://example.com/a.zip',
            'mode'        => 'skip',
        ]);
        AdminAssetImport::whereKey($stale->id)->update([
            'updated_at' => now()->subMinutes(AdminAssetImport::STALE_AFTER_MINUTES + 1),
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.assets.imports'))
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonPath('imports.0.status', 'failed');
    }

    public function test_admin_can_cancel_an_active_import(): void
    {
        $import = AdminAssetImport::create([
            'status'      => 'processing',
            'source_type' => 'upload',
            'source'      => 'stuck.zip',
            'mode'        => 'skip',
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.assets.imports.cancel', $import))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('import.status', 'failed');

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame('Cancelled by an administrator.', $import->error);
        $this->assertNotNull($import->completed_at);
    }

    public function test_cancel_rejects_finished_imports_and_requires_admin(): void
    {
        $active = AdminAssetImport::create([
            'status'      => 'processing',
            'source_type' => 'upload',
            'source'      => 'stuck.zip',
            'mode'        => 'skip',
        ]);

        // Guest and plain web users are bounced (before any admin login,
        // since actingAs persists for the rest of the test).
        $this->post(route('admin.assets.imports.cancel', $active))->assertRedirect();
        $this->actingAs(User::factory()->create(), 'web')
            ->post(route('admin.assets.imports.cancel', $active))
            ->assertRedirect();
        $this->assertSame('processing', $active->fresh()->status);

        $done = AdminAssetImport::create([
            'status'      => 'completed',
            'source_type' => 'upload',
            'source'      => 'done.zip',
            'mode'        => 'skip',
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.assets.imports.cancel', $done))
            ->assertStatus(422);
    }

    public function test_job_does_not_resurrect_a_cancelled_import(): void
    {
        $this->fakeLocalDisk();
        $zipPath = $this->buildFixtureZip();

        $import = AdminAssetImport::create([
            'admin_id'    => $this->makeAdmin()->id,
            'status'      => 'pending',
            'source_type' => 'upload',
            'source'      => 'fixture.zip',
            'mode'        => 'skip',
            'zip_path'    => $zipPath,
        ]);

        // Simulate a cancel landing after the job loaded the row: flip the
        // DB status to failed behind the in-memory model's back.
        AdminAssetImport::whereKey($import->id)->update([
            'status'       => 'failed',
            'error'        => 'Cancelled by an administrator.',
            'completed_at' => now(),
        ]);

        // The job bails early because the row is no longer pending.
        (new ProcessAdminAssetZipImportJob($import->id))->handle();

        $import->refresh();
        $this->assertSame('failed', $import->status);
        $this->assertSame('Cancelled by an administrator.', $import->error);
    }

    /* ───────────────── retrying failed imports ───────────────── */

    public function test_failed_url_import_can_be_retried(): void
    {
        Queue::fake();
        $failed = AdminAssetImport::create([
            'status'      => 'failed',
            'source_type' => 'url',
            'source'      => 'https://example.com/archive.zip',
            'mode'        => 'overwrite',
            'error'       => 'worker lost',
        ]);

        $this->actingAs($this->makeAdmin(), 'admin')
            ->post(route('admin.assets.imports.retry', $failed))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('import.status', 'pending')
            ->assertJsonPath('import.source_type', 'url')
            ->assertJsonPath('import.source', 'https://example.com/archive.zip')
            ->assertJsonPath('import.mode', 'overwrite');

        Queue::assertPushed(ProcessAdminAssetZipImportJob::class, 1);
        // A fresh row was minted; the failed one is untouched.
        $this->assertSame(2, AdminAssetImport::count());
        $this->assertSame('failed', $failed->fresh()->status);
    }

    public function test_retry_rejected_for_upload_source_non_failed_and_when_active(): void
    {
        Queue::fake();
        $admin = $this->makeAdmin();

        // Upload-sourced failures cannot be retried — the temp zip is gone.
        $upload = AdminAssetImport::create([
            'status' => 'failed', 'source_type' => 'upload', 'source' => 'a.zip', 'mode' => 'skip',
        ]);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.assets.imports.retry', $upload))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Non-failed imports cannot be retried.
        $done = AdminAssetImport::create([
            'status' => 'completed', 'source_type' => 'url', 'source' => 'https://example.com/b.zip', 'mode' => 'skip',
        ]);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.assets.imports.retry', $done))
            ->assertStatus(422);

        // No retry while another import is running.
        $failed = AdminAssetImport::create([
            'status' => 'failed', 'source_type' => 'url', 'source' => 'https://example.com/c.zip', 'mode' => 'skip',
        ]);
        AdminAssetImport::create([
            'status' => 'processing', 'source_type' => 'url', 'source' => 'https://example.com/d.zip', 'mode' => 'skip',
        ]);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.assets.imports.retry', $failed))
            ->assertStatus(422);

        Queue::assertNotPushed(ProcessAdminAssetZipImportJob::class);
    }

    public function test_import_routes_require_admin_auth(): void
    {
        Queue::fake();
        $payload = ['file' => UploadedFile::fake()->create('a.zip', 10, 'application/zip')];

        $failed = AdminAssetImport::create([
            'status' => 'failed', 'source_type' => 'url', 'source' => 'https://example.com/x.zip', 'mode' => 'skip',
        ]);

        // Guest is bounced from all three routes.
        $this->post(route('admin.assets.import-zip'), $payload)->assertRedirect();
        $this->get(route('admin.assets.imports'))->assertRedirect();
        $this->post(route('admin.assets.imports.retry', $failed))->assertRedirect();

        // A plain front-end user (web guard) is not an admin either.
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->post(route('admin.assets.import-zip'), $payload)
            ->assertRedirect();
        $this->actingAs($user, 'web')
            ->get(route('admin.assets.imports'))
            ->assertRedirect();

        Queue::assertNotPushed(ProcessAdminAssetZipImportJob::class);
        // Only the pre-seeded failed fixture exists — no new import rows were minted.
        $this->assertSame(1, AdminAssetImport::count());
    }
}
