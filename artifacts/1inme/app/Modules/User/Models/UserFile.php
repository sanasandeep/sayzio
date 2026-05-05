<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class UserFile extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'original_name', 'filename', 'mime_type',
        'size_bytes', 'type', 'disk', 'path',
        'scan_status', 'scan_reason', 'scan_meta',
        'scanned_at', 'quarantined_at', 'scan_admin_reviewed',
    ];

    protected $appends = ['url', 'url_path', 'size_human'];

    protected $casts = [
        'scan_meta'           => 'array',
        'scanned_at'          => 'datetime',
        'quarantined_at'      => 'datetime',
        'scan_admin_reviewed' => 'boolean',
    ];

    const ALLOWED_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/aac', 'audio/mp4'],
        'document' => [
            'application/pdf',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    const ALLOWED_EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'video' => ['mp4', 'webm', 'ogg', 'mov'],
        'audio' => ['mp3', 'wav', 'ogg', 'webm', 'aac', 'm4a'],
        'document' => ['pdf', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return url('/f/' . $this->id . '/' . $this->filename);
    }

    public function getUrlPathAttribute(): string
    {
        return '/f/' . $this->id . '/' . $this->filename;
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    public function deleteFile(): bool
    {
        $storageDisk = $this->disk === 'public' ? 'public' : ($this->disk === 's3' ? 's3' : 'user_files');
        Storage::disk($storageDisk)->delete($this->path);
        return $this->delete();
    }

    public static function detectType(string $mime): string
    {
        foreach (self::ALLOWED_TYPES as $type => $mimes) {
            if (in_array($mime, $mimes)) return $type;
        }
        return 'document';
    }

    public static function getAllAllowedMimes(): array
    {
        return array_merge(...array_values(self::ALLOWED_TYPES));
    }

    public static function getAllAllowedExtensions(): array
    {
        return array_merge(...array_values(self::ALLOWED_EXTENSIONS));
    }

    /**
     * Centralised vault-upload helper. Every controller that previously wrote
     * to the public disk should call this so that quota, per-plan size limits
     * and storage permissions are enforced uniformly.
     *
     * Options:
     *   enforce_allowlist (bool, default true) — reject mimes/exts outside
     *     ALLOWED_TYPES. Set false for File Share / verification proofs which
     *     legitimately accept arbitrary file types.
     *   max_size_mb (int|null) — overrides the per-plan max_file_size_mb when
     *     the calling surface has its own contractual limit (e.g. verification
     *     logo capped at 2MB regardless of plan).
     *
     * Throws RuntimeException with a user-safe message on validation failure.
     */
    public static function createFromUpload(UploadedFile $file, $user, array $options = []): self
    {
        $enforceAllowlist = $options['enforce_allowlist'] ?? true;
        $maxSizeOverride  = $options['max_size_mb'] ?? null;
        $uploadKey        = $options['upload_key'] ?? null;
        $policyExtensions = null;

        // Holders of `user.files.access_any` bypass the global
        // mime/extension allowlist as well.
        if (method_exists($user, 'hasPermission') && $user->hasPermission('user.files.access_any')) {
            $enforceAllowlist = false;
        }

        // When an upload_key (UploadPolicy context) is provided we let the
        // per-context plan override authoritatively drive both the size cap
        // and the extension allowlist. This makes plan-level
        // features.upload_limits overrides binding at the storage layer.
        if ($uploadKey !== null) {
            $policy = \App\Services\UploadPolicy::for($uploadKey, $user);
            if ($maxSizeOverride === null && !empty($policy['max_mb'])) {
                $maxSizeOverride = (int) $policy['max_mb'];
            }
            if (!empty($policy['extensions'])) {
                $policyExtensions = array_map('strtolower', $policy['extensions']);
            }
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $size = (int) $file->getSize();

        if ($policyExtensions !== null && !in_array($ext, $policyExtensions, true)) {
            throw new RuntimeException('File extension not allowed for this upload.');
        }

        if ($enforceAllowlist) {
            if (! in_array($mime, self::getAllAllowedMimes(), true)) {
                throw new RuntimeException('File type not allowed.');
            }
            if (! in_array($ext, self::getAllAllowedExtensions(), true)) {
                throw new RuntimeException('File extension not allowed.');
            }
        }

        $maxFileSizeMb = $maxSizeOverride !== null
            ? (int) $maxSizeOverride
            : (int) $user->getPlanFeature('max_file_size_mb', 5);
        $maxFileBytes = $maxFileSizeMb * 1048576;
        if ($size > $maxFileBytes) {
            throw new RuntimeException("File exceeds maximum size of {$maxFileSizeMb}MB.");
        }

        if (method_exists($user, 'getStorageRemainingBytes')) {
            $remaining = (int) $user->getStorageRemainingBytes();
            if ($size > $remaining) {
                $usedMb  = round(((int) $user->getStorageUsedBytes()) / 1048576, 1);
                $limitMb = round(((int) $user->getStorageLimitBytes()) / 1048576);
                throw new RuntimeException("Storage quota exceeded. Used {$usedMb}MB of {$limitMb}MB.");
            }
        }

        $fileType = self::detectType($mime);
        $disk     = config('filesystems.default') === 's3' ? 's3' : 'user_files';
        $folder   = "{$user->id}/{$fileType}s";
        $filename = (string) Str::uuid() . ($ext ? '.' . $ext : '');

        // Optionally downscale + re-encode raster images before storing
        // so the vault doesn't carry full-resolution camera dumps. Only
        // kicks in when the caller opts in, and is silently skipped on
        // any failure — the original is then stored as-is.
        $compressedTmp = null;
        if (!empty($options['compress_image'])) {
            $compressedTmp = self::compressUploadedImage($file, $mime, [
                'max_width'  => (int) ($options['max_width']  ?? 800),
                'max_height' => (int) ($options['max_height'] ?? 800),
                'quality'    => (int) ($options['quality']    ?? 85),
            ]);
        }

        if ($compressedTmp !== null) {
            $storedPath = $folder . '/' . $filename;
            $stream = @fopen($compressedTmp, 'rb');
            if ($stream !== false) {
                Storage::disk($disk)->put($storedPath, $stream);
                @fclose($stream);
            } else {
                Storage::disk($disk)->put($storedPath, (string) @file_get_contents($compressedTmp));
            }
            $size = (int) (@filesize($compressedTmp) ?: $size);
            @unlink($compressedTmp);
        } else {
            $storedPath = $file->storeAs($folder, $filename, $disk);
        }

        $userFile = self::create([
            'user_id'       => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'filename'      => $filename,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'type'          => $fileType,
            'disk'          => $disk,
            'path'          => $storedPath,
            'scan_status'   => 'pending',
        ]);

        // Run virus + phishing heuristics inline so the file lands in
        // either `clean` or `flagged` before the response returns.
        // Skipped on demand via UPLOAD_SCANNER_DISABLED for tests / CI.
        if (!($options['skip_scan'] ?? false) && !env('UPLOAD_SCANNER_DISABLED', false)) {
            try {
                app(\App\Modules\User\Services\Uploads\UploadScanner::class)->scan($userFile);
            } catch (\Throwable $e) {
                $userFile->forceFill([
                    'scan_status' => 'flagged',
                    'scan_reason' => 'scan_error',
                    'scan_meta'   => ['error' => $e->getMessage()],
                    'scanned_at'  => now(),
                    'quarantined_at' => now(),
                ])->save();
            }
        }

        return $userFile;
    }

    /**
     * Re-optimize an already-stored raster image in place. Reads the
     * current bytes, downscales/re-encodes them, and only overwrites
     * (and updates `size_bytes`) if the new bytes are actually smaller.
     * Used to lazily shrink header photos that were uploaded before
     * the upload-time compression existed.
     *
     * Returns true when the stored bytes were replaced.
     */
    public function reoptimizeImageInPlace(int $maxWidth = 800, int $maxHeight = 800, int $quality = 85): bool
    {
        if ($this->type !== 'image') return false;

        $mime = strtolower((string) $this->mime_type);
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            return false;
        }

        $storageDisk = $this->disk === 'public' ? 'public' : ($this->disk === 's3' ? 's3' : 'user_files');
        $disk = Storage::disk($storageDisk);

        try {
            if (!$disk->exists($this->path)) return false;
            $bytes = $disk->get($this->path);
            if (!is_string($bytes) || $bytes === '') return false;
        } catch (\Throwable $e) {
            return false;
        }

        $originalSize = strlen($bytes);

        // Stage the bytes to a real file so compressImageBytes can read
        // EXIF orientation off it — re-encoding strips EXIF, so legacy
        // phone photos with non-default orientation tags would otherwise
        // come out rotated after a lazy reoptimization.
        $exifSource = null;
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $exifSource = tempnam(sys_get_temp_dir(), 'ufexif_');
            if ($exifSource !== false) {
                if (@file_put_contents($exifSource, $bytes) === false) {
                    @unlink($exifSource);
                    $exifSource = null;
                }
            } else {
                $exifSource = null;
            }
        }

        $newBytes = self::compressImageBytes($bytes, $mime, [
            'max_width'  => $maxWidth,
            'max_height' => $maxHeight,
            'quality'    => $quality,
        ], $exifSource);

        if ($exifSource !== null) @unlink($exifSource);

        if ($newBytes === null) return false;
        $newSize = strlen($newBytes);
        if ($newSize <= 0 || $newSize >= $originalSize) return false;

        try {
            $disk->put($this->path, $newBytes);
        } catch (\Throwable $e) {
            return false;
        }

        $this->size_bytes = $newSize;
        $this->save();
        return true;
    }

    /**
     * Compress an UploadedFile's raster image to fit within max dimensions.
     * Returns a temp file path with the processed bytes, or null when
     * compression isn't applicable or fails (caller stores the original).
     */
    protected static function compressUploadedImage(UploadedFile $file, string $mime, array $options): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;
        $mime = strtolower($mime);
        if (!in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        $sourcePath = $file->getRealPath();
        if (!$sourcePath || !is_readable($sourcePath)) return null;
        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false || $bytes === '') return null;

        $newBytes = self::compressImageBytes($bytes, $mime, $options, $sourcePath);
        if ($newBytes === null) return null;

        // Only keep the processed bytes if they're smaller than the
        // original — re-encoding a perfectly-sized small JPEG can
        // sometimes grow it, and we'd rather store the original.
        if (strlen($newBytes) >= strlen($bytes)) return null;

        $tmp = tempnam(sys_get_temp_dir(), 'ufimg_');
        if ($tmp === false) return null;
        if (@file_put_contents($tmp, $newBytes) === false) {
            @unlink($tmp);
            return null;
        }
        return $tmp;
    }

    /**
     * Core GD-based resize+re-encode. Returns the processed bytes or
     * null on any failure / unsupported mime. Honors EXIF orientation
     * for JPEGs so phone photos don't end up sideways. When a source
     * file path is provided and the image is already within bounds,
     * the bytes are still re-encoded so quality settings apply.
     */
    protected static function compressImageBytes(string $bytes, string $mime, array $options, ?string $exifSourcePath = null): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;

        $maxWidth  = (int) ($options['max_width']  ?? 800);
        $maxHeight = (int) ($options['max_height'] ?? 800);
        $quality   = (int) max(1, min(100, $options['quality'] ?? 85));
        if ($maxWidth <= 0 || $maxHeight <= 0) return null;

        $img = @imagecreatefromstring($bytes);
        if ($img === false) return null;

        try {
            if (($mime === 'image/jpeg' || $mime === 'image/jpg')
                && $exifSourcePath !== null
                && function_exists('exif_read_data')) {
                $exif = @exif_read_data($exifSourcePath);
                $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : 0;
                if ($orientation > 1 && $orientation <= 8) {
                    switch ($orientation) {
                        case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
                        case 3: $img = imagerotate($img, 180, 0); break;
                        case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
                        case 5: imageflip($img, IMG_FLIP_VERTICAL); $img = imagerotate($img, -90, 0); break;
                        case 6: $img = imagerotate($img, -90, 0); break;
                        case 7: imageflip($img, IMG_FLIP_HORIZONTAL); $img = imagerotate($img, -90, 0); break;
                        case 8: $img = imagerotate($img, 90, 0); break;
                    }
                }
            }

            $w = imagesx($img);
            $h = imagesy($img);
            if ($w <= 0 || $h <= 0) {
                imagedestroy($img);
                return null;
            }

            $scale = min(1.0, $maxWidth / $w, $maxHeight / $h);
            $newW = max(1, (int) floor($w * $scale));
            $newH = max(1, (int) floor($h * $scale));

            if ($scale < 1.0) {
                $resized = imagecreatetruecolor($newW, $newH);
                if ($mime === 'image/png' || $mime === 'image/webp') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                    imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
                }
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'ufenc_');
            if ($tmp === false) {
                imagedestroy($img);
                return null;
            }
            $ok = false;
            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    $ok = imagejpeg($img, $tmp, $quality);
                    break;
                case 'image/png':
                    imagealphablending($img, false);
                    imagesavealpha($img, true);
                    $pngLevel = (int) max(0, min(9, round((100 - $quality) / 11)));
                    $ok = imagepng($img, $tmp, $pngLevel);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        $ok = imagewebp($img, $tmp, $quality);
                    }
                    break;
            }
            imagedestroy($img);

            if (!$ok) {
                @unlink($tmp);
                return null;
            }
            $out = @file_get_contents($tmp);
            @unlink($tmp);
            if (!is_string($out) || $out === '') return null;
            return $out;
        } catch (\Throwable $e) {
            if (is_resource($img) || $img instanceof \GdImage) {
                @imagedestroy($img);
            }
            return null;
        }
    }

    /**
     * Vault-write raw bytes (e.g. a signature PNG generated client-side).
     * Bypasses the mime allowlist by design — caller is expected to have
     * validated the bytes (e.g. PNG magic header check).
     */
    public static function createFromBytes(string $bytes, string $originalName, string $mime, $user, array $opts = []): self
    {
        $size = strlen($bytes);

        // Per-plan max single-file size (matches createFromUpload).
        $maxMb = (int) ($opts['max_size_mb']
            ?? (method_exists($user, 'getPlanFeature') ? $user->getPlanFeature('max_file_size_mb', 5) : 5));
        if ($maxMb > 0 && $size > $maxMb * 1024 * 1024) {
            throw new RuntimeException("File exceeds the per-file limit of {$maxMb} MB.");
        }

        if (method_exists($user, 'getStorageRemainingBytes')) {
            $remaining = (int) $user->getStorageRemainingBytes();
            if ($size > $remaining) {
                throw new RuntimeException('Storage quota exceeded.');
            }
        }

        $fileType = self::detectType($mime);
        $disk     = config('filesystems.default') === 's3' ? 's3' : 'user_files';
        $folder   = "{$user->id}/{$fileType}s";
        $ext      = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin';
        $filename = (string) Str::uuid() . '.' . strtolower($ext);
        $path     = $folder . '/' . $filename;

        Storage::disk($disk)->put($path, $bytes);

        $userFile = self::create([
            'user_id'       => $user->id,
            'original_name' => $originalName,
            'filename'      => $filename,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'type'          => $fileType,
            'disk'          => $disk,
            'path'          => $path,
            'scan_status'   => 'pending',
        ]);

        if (!($opts['skip_scan'] ?? false) && !env('UPLOAD_SCANNER_DISABLED', false)) {
            try {
                app(\App\Modules\User\Services\Uploads\UploadScanner::class)->scan($userFile);
            } catch (\Throwable $e) {
                $userFile->forceFill([
                    'scan_status' => 'flagged',
                    'scan_reason' => 'scan_error',
                    'scan_meta'   => ['error' => $e->getMessage()],
                    'scanned_at'  => now(),
                    'quarantined_at' => now(),
                ])->save();
            }
        }

        return $userFile;
    }

    /**
     * Helpers for the scan-status badges rendered in inbox + form views,
     * and for the "is this safe to download right now" gate enforced by
     * UserFileController::serve.
     */
    public function isPendingScan(): bool { return $this->scan_status === 'pending'; }
    public function isScanClean(): bool   { return in_array($this->scan_status, ['clean', 'skipped'], true) || $this->scan_status === null; }
    public function isFlagged(): bool     { return $this->scan_status === 'flagged'; }

    public function isHighRiskExtension(): bool
    {
        $ext = strtolower((string) pathinfo($this->original_name, PATHINFO_EXTENSION));
        return \App\Modules\User\Services\Uploads\UploadScanner::isHighRisk($ext);
    }

    public function scanReasonLabel(): string
    {
        return \App\Modules\User\Services\Uploads\UploadScanner::reasonLabel($this->scan_reason);
    }

    /** Map a /f/{id}/{filename} URL back to the underlying record, or null. */
    public static function fromServeUrl(?string $url): ?self
    {
        if (!is_string($url) || $url === '') return null;
        if (preg_match('#/f/(\d+)/#', $url, $m)) {
            return self::find((int) $m[1]);
        }
        return null;
    }
}
