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
class AdminAssetZipImportTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_import_routes_require_admin_auth(): void
    {
        Queue::fake();
        $payload = ['file' => UploadedFile::fake()->create('a.zip', 10, 'application/zip')];

        // Guest is bounced from both routes.
        $this->post(route('admin.assets.import-zip'), $payload)->assertRedirect();
        $this->get(route('admin.assets.imports'))->assertRedirect();

        // A plain front-end user (web guard) is not an admin either.
        $user = User::factory()->create();
        $this->actingAs($user, 'web')
            ->post(route('admin.assets.import-zip'), $payload)
            ->assertRedirect();
        $this->actingAs($user, 'web')
            ->get(route('admin.assets.imports'))
            ->assertRedirect();

        Queue::assertNotPushed(ProcessAdminAssetZipImportJob::class);
        $this->assertSame(0, AdminAssetImport::count());
    }
}
