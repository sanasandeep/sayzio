<?php

namespace App\Jobs;

use App\Modules\Admin\Models\AdminAsset;
use App\Modules\Admin\Models\AdminAssetFolder;
use App\Modules\Admin\Models\AdminAssetImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Background extraction for an Asset Vault zip import.
 *
 * The HTTP request only records where the archive is (an uploaded temp file
 * or a remote URL / s3:// location); this job downloads it if needed, walks
 * the archive entry-by-entry (streaming — never inflates the whole zip into
 * memory), validates each entry (image allowlist, per-file size cap,
 * zip-slip path sanitisation) and files accepted images onto the active
 * admin-assets disk, mirroring the archive's top-level folders as vault
 * folders. Re-imports are idempotent: the storage path is derived from the
 * entry's path inside the archive, so a duplicate is either skipped or
 * overwritten depending on the import's mode.
 */
class ProcessAdminAssetZipImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1h ceiling for very large archives
    public int $tries = 1;      // failures are parked on the import row

    /** Max archive size we will download/process (bytes). */
    public const MAX_ZIP_BYTES = 4 * 1024 * 1024 * 1024; // 4 GB

    /** Max size per extracted image (bytes). */
    public const MAX_ENTRY_BYTES = 30 * 1024 * 1024; // 30 MB

    /** Image extensions accepted from the archive. */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

    private const EXT_MIME = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'avif' => 'image/avif',
    ];

    public function __construct(public int $importId) {}

    public function handle(): void
    {
        $import = AdminAssetImport::find($this->importId);
        if (!$import || !in_array($import->status, ['pending', 'downloading'], true)) {
            return;
        }

        $import->forceFill(['started_at' => $import->started_at ?? now()])->save();

        $downloadedTmp = null;
        try {
            // ── 1. Get the archive onto local disk ────────────────
            if ($import->source_type === 'url') {
                $import->forceFill(['status' => 'downloading'])->save();
                $downloadedTmp = $this->fetchRemoteZip($import);
                $zipPath = $downloadedTmp;
            } else {
                $zipPath = $import->zip_path;
            }

            if (!$zipPath || !is_file($zipPath)) {
                throw new \RuntimeException('The zip archive could not be found on disk.');
            }
            $zipSize = (int) (@filesize($zipPath) ?: 0);
            if ($zipSize > static::MAX_ZIP_BYTES) {
                throw new \RuntimeException('Archive exceeds the 4 GB import limit.');
            }
            $import->forceFill(['status' => 'processing', 'zip_size_bytes' => $zipSize])->save();

            // ── 2. Walk the archive ───────────────────────────────
            $zip = new ZipArchive();
            $rc = $zip->open($zipPath, ZipArchive::RDONLY);
            if ($rc !== true) {
                throw new \RuntimeException('The file is not a readable zip archive (code ' . $rc . ').');
            }

            $import->total_entries = $zip->numFiles;
            $import->save();

            $disk = AdminAsset::diskName();
            $folderCache = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    $import->processed_entries++;
                    continue;
                }
                $this->processEntry($import, $zip, $i, $stat, $disk, $folderCache);
                $import->processed_entries++;
                if ($import->processed_entries % 20 === 0) {
                    // Abort promptly if an admin cancelled the import (or the
                    // stale reaper failed it) while we were extracting.
                    if (AdminAssetImport::whereKey($import->id)->value('status') === 'failed') {
                        $zip->close();
                        return;
                    }
                    $import->save();
                }
            }
            $zip->close();

            // Don't resurrect an import that was cancelled/reaped mid-run.
            if (AdminAssetImport::whereKey($import->id)->value('status') === 'failed') {
                return;
            }

            $import->forceFill([
                'status'       => 'completed',
                'completed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            // Don't clobber an admin cancellation that landed mid-run: a
            // cancelled row stays 'cancelled' (the abort is not a failure).
            if (($import->fresh()->status ?? null) === 'cancelled') {
                $import->refresh();
                if ($import->completed_at === null) {
                    $import->forceFill(['completed_at' => now()])->save();
                }
            } else {
                $import->forceFill([
                    'status'       => 'failed',
                    'error'        => Str::limit($e->getMessage(), 500),
                    'completed_at' => now(),
                ])->save();
            }
        } finally {
            // Always clean up the archive temp files, success or failure.
            if ($downloadedTmp && is_file($downloadedTmp)) {
                @unlink($downloadedTmp);
            }
            if ($import->zip_path && is_file($import->zip_path)) {
                @unlink($import->zip_path);
            }
            if ($import->zip_path !== null) {
                $import->forceFill(['zip_path' => null])->save();
            }
        }
    }

    /* ───────────────────────── entry processing ───────────────────────── */

    private function processEntry(AdminAssetImport $import, ZipArchive $zip, int $index, array $stat, string $disk, array &$folderCache): void
    {
        $rawName = (string) ($stat['name'] ?? '');
        if ($rawName === '' || str_ends_with($rawName, '/')) {
            return; // directory entry
        }

        // Zip-slip / path sanitisation: normalise separators, reject any
        // traversal or absolute component before we derive anything from it.
        $name = str_replace('\\', '/', $rawName);
        $segments = array_values(array_filter(explode('/', $name), fn ($s) => $s !== '' && $s !== '.'));
        if ($segments === [] || in_array('..', $segments, true) || str_starts_with($name, '/')) {
            $import->noteSkipped($rawName, 'Unsafe path');
            return;
        }
        $cleanPath = implode('/', $segments);
        $basename  = end($segments);

        // Ignore OS junk quietly (no summary noise).
        if (str_starts_with($basename, '._') || $basename === '.DS_Store' || $basename === 'Thumbs.db'
            || ($segments[0] ?? '') === '__MACOSX') {
            return;
        }

        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
            $import->noteSkipped($cleanPath, 'Not a supported image type');
            return;
        }

        $entrySize = (int) ($stat['size'] ?? 0);
        if ($entrySize <= 0) {
            $import->noteSkipped($cleanPath, 'Empty file');
            return;
        }
        if ($entrySize > self::MAX_ENTRY_BYTES) {
            $import->noteSkipped($cleanPath, 'Exceeds the 30 MB per-image limit');
            return;
        }

        // Vault folder = first directory inside the archive (avatars/… ⇒ "avatars").
        $folderSlug = null;
        if (count($segments) > 1) {
            $folderName = $segments[0];
            $slug = Str::slug($folderName, '-');
            if ($slug !== '') {
                if (!array_key_exists($slug, $folderCache)) {
                    AdminAssetFolder::firstOrCreate(['slug' => $slug], ['name' => $folderName]);
                    $folderCache[$slug] = true;
                }
                $folderSlug = $slug;
            }
        }

        // Deterministic storage path keyed on the entry's archive path, so
        // re-importing the same zip maps to the same object (idempotency).
        $filename   = sha1($cleanPath) . '.' . $ext;
        $storedPath = 'admin-assets/images/' . ($folderSlug ?: 'imported') . '/' . $filename;

        $existing = AdminAsset::where('path', $storedPath)->first();
        if ($existing && $import->mode !== 'overwrite') {
            $import->noteSkipped($cleanPath, 'Already imported (skipped)');
            return;
        }

        // ── Extract to a bounded temp file (streamed) ─────────────
        $tmp = tempnam(sys_get_temp_dir(), 'vaultzip_');
        if ($tmp === false) {
            $import->noteSkipped($cleanPath, 'Could not create temp file');
            return;
        }

        try {
            $in = $zip->getStream($rawName);
            if ($in === false) {
                $import->noteSkipped($cleanPath, 'Could not read entry');
                return;
            }
            $out = fopen($tmp, 'wb');
            $written = 0;
            while (!feof($in)) {
                $chunk = fread($in, 1024 * 512);
                if ($chunk === false) break;
                $written += strlen($chunk);
                if ($written > self::MAX_ENTRY_BYTES) { // zip-bomb guard: trust bytes, not headers
                    fclose($in);
                    fclose($out);
                    $import->noteSkipped($cleanPath, 'Exceeds the 30 MB per-image limit');
                    return;
                }
                fwrite($out, $chunk);
            }
            fclose($in);
            fclose($out);

            if (!$this->looksLikeImage($tmp, $ext)) {
                $import->noteSkipped($cleanPath, 'File content is not a valid image');
                return;
            }

            $mime = self::EXT_MIME[$ext] ?? 'application/octet-stream';

            // Record pixel dimensions while the bytes are still on local disk
            // so the vault can show / filter by size later.
            [$width, $height] = AdminAsset::probeImageDimensions($tmp, $ext);

            $stream = fopen($tmp, 'rb');
            $ok = Storage::disk($disk)->put($storedPath, $stream);
            if (is_resource($stream)) fclose($stream);
            if ($ok === false) {
                $import->noteSkipped($cleanPath, 'Storage write failed');
                return;
            }

            $attrs = [
                'admin_id'      => $import->admin_id,
                'original_name' => $basename,
                'filename'      => $filename,
                'mime_type'     => $mime,
                'size_bytes'    => $written,
                'type'          => 'image',
                'width'         => $width,
                'height'        => $height,
                'disk'          => $disk,
                'path'          => $storedPath,
                'folder'        => $folderSlug,
                'label'         => pathinfo($basename, PATHINFO_FILENAME),
                'description'   => 'Imported from zip: ' . Str::limit($cleanPath, 180),
                // Template assets must resolve publicly to be usable in designs.
                'is_public'     => true,
            ];

            if ($existing) {
                $existing->update($attrs);
                $import->overwritten_count++;
            } else {
                AdminAsset::create($attrs);
                $import->imported_count++;
            }
        } finally {
            @unlink($tmp);
        }
    }

    /** Cheap content sniff: raster via getimagesize, svg via tag scan. */
    private function looksLikeImage(string $path, string $ext): bool
    {
        if ($ext === 'svg') {
            $head = (string) @file_get_contents($path, false, null, 0, 4096);
            return stripos($head, '<svg') !== false;
        }
        if ($ext === 'avif') {
            // getimagesize may not know avif on this build; check the ftyp box.
            $head = (string) @file_get_contents($path, false, null, 0, 32);
            return str_contains($head, 'ftypavif') || @getimagesize($path) !== false;
        }
        return @getimagesize($path) !== false;
    }

    /* ───────────────────────── remote fetch ───────────────────────── */

    /**
     * Download the archive from the import's source (https URL or an
     * s3://bucket/key on the configured bucket) into a local temp file,
     * enforcing the size cap while streaming. Returns the temp path.
     */
    protected function fetchRemoteZip(AdminAssetImport $import): string
    {
        $source = trim((string) $import->source);

        $tmp = tempnam(sys_get_temp_dir(), 'vaultzipdl_');
        if ($tmp === false) {
            throw new \RuntimeException('Could not allocate a temp file for the download.');
        }

        try {
            if (str_starts_with($source, 's3://')) {
                $this->downloadFromS3($source, $tmp);
            } else {
                $this->downloadFromHttp($source, $tmp);
            }
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw $e;
        }

        return $tmp;
    }

    protected function downloadFromS3(string $source, string $dest): void
    {
        // s3://bucket/key — only the configured bucket is allowed.
        $rest = substr($source, 5);
        $slash = strpos($rest, '/');
        if ($slash === false || $slash === 0) {
            throw new \RuntimeException('S3 location must look like s3://bucket/path/to/archive.zip');
        }
        $bucket = substr($rest, 0, $slash);
        $key    = ltrim(substr($rest, $slash + 1), '/');

        $configuredBucket = (string) (config('filesystems.disks.s3.bucket') ?: '');
        if ($configuredBucket === '' || $bucket !== $configuredBucket) {
            throw new \RuntimeException('Only the configured S3 bucket (' . ($configuredBucket ?: 'none') . ') can be read.');
        }

        $stream = Storage::disk('s3')->readStream($key);
        if (!$stream) {
            throw new \RuntimeException('Could not read the archive from S3 (' . $key . ').');
        }
        $out = fopen($dest, 'wb');
        $written = 0;
        while (!feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) break;
            $written += strlen($chunk);
            if ($written > static::MAX_ZIP_BYTES) {
                fclose($stream);
                fclose($out);
                throw new \RuntimeException('Archive exceeds the 4 GB import limit.');
            }
            fwrite($out, $chunk);
        }
        fclose($stream);
        fclose($out);
        if ($written === 0) {
            throw new \RuntimeException('The S3 object was empty.');
        }
    }

    protected function downloadFromHttp(string $source, string $dest): void
    {
        $current = $source;
        // Follow redirects manually so every hop is re-validated (SSRF guard).
        for ($hop = 0; $hop < 5; $hop++) {
            $this->assertSafeHttpUrl($current);

            $ch = curl_init($current);
            $out = fopen($dest, 'wb');
            $written = 0;
            $tooBig = false;
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT        => 1800,
                CURLOPT_FAILONERROR    => false,
                CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use ($out, &$written, &$tooBig) {
                    $written += strlen($chunk);
                    if ($written > static::MAX_ZIP_BYTES) {
                        $tooBig = true;
                        return -1; // abort transfer
                    }
                    return fwrite($out, $chunk);
                },
            ]);
            curl_exec($ch);
            $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $redirect = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
            $err      = curl_error($ch);
            curl_close($ch);
            fclose($out);

            if ($tooBig) {
                throw new \RuntimeException('Archive exceeds the 4 GB import limit.');
            }
            if ($status >= 300 && $status < 400 && $redirect !== '') {
                $current = $redirect;
                continue; // re-validated at loop top
            }
            if ($status >= 200 && $status < 300 && $written > 0) {
                return;
            }
            throw new \RuntimeException(
                'Download failed (HTTP ' . ($status ?: 'error') . ($err ? ': ' . $err : '') . ').'
            );
        }
        throw new \RuntimeException('Too many redirects while downloading the archive.');
    }

    /** Reject non-https(+http) schemes and private / internal hosts. */
    protected function assertSafeHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Only http(s) URLs or s3://bucket/key locations are supported.');
        }
        $ips = @gethostbynamel($host) ?: [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        }
        if ($ips === []) {
            throw new \RuntimeException('Could not resolve the download host.');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \RuntimeException('The download host resolves to a private/internal address, which is not allowed.');
            }
        }
    }
}
