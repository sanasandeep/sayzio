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
    ];

    protected $appends = ['url', 'url_path', 'size_human'];

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

        // Super admins bypass the global mime/extension allowlist as well.
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
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

        $storedPath = $file->storeAs($folder, $filename, $disk);

        return self::create([
            'user_id'       => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'filename'      => $filename,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'type'          => $fileType,
            'disk'          => $disk,
            'path'          => $storedPath,
        ]);
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

        return self::create([
            'user_id'       => $user->id,
            'original_name' => $originalName,
            'filename'      => $filename,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'type'          => $fileType,
            'disk'          => $disk,
            'path'          => $path,
        ]);
    }
}
