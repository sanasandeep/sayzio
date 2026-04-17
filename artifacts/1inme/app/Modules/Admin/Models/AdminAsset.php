<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AdminAsset extends Model
{
    protected $fillable = [
        'admin_id', 'original_name', 'filename', 'mime_type',
        'size_bytes', 'type', 'disk', 'path', 'folder',
        'label', 'description', 'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'size_bytes' => 'integer',
    ];

    protected $appends = ['url', 'url_path', 'size_human'];

    /**
     * Detect a coarse file family from the mime so the UI can group / filter.
     */
    public static function detectType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'], true)) {
            return 'archive';
        }
        if ($mime === 'application/pdf' || str_contains($mime, 'word') || str_contains($mime, 'excel')
            || str_contains($mime, 'spreadsheet') || str_contains($mime, 'presentation') || str_contains($mime, 'opendocument')
            || str_starts_with($mime, 'text/')) {
            return 'document';
        }
        return 'other';
    }

    public static function diskName(): string
    {
        return (string) config('filesystems.admin_assets_disk', 'admin_assets');
    }

    public function getUrlAttribute(): string
    {
        return url($this->getUrlPathAttribute());
    }

    public function getUrlPathAttribute(): string
    {
        return '/admin-assets/' . $this->id . '/' . $this->filename;
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }

    public function deleteFile(): bool
    {
        try {
            Storage::disk($this->disk)->delete($this->path);
        } catch (\Throwable $e) {
            // Storage may already be gone — still drop the row.
        }
        return $this->delete();
    }

    public static function createFromUpload(UploadedFile $file, $admin = null, array $options = []): self
    {
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $size = (int) $file->getSize();

        $maxMb = (int) ($options['max_size_mb'] ?? config('admin_assets.max_size_mb', 256));
        if ($maxMb > 0 && $size > $maxMb * 1048576) {
            throw new RuntimeException("File exceeds the maximum size of {$maxMb} MB.");
        }

        $type = self::detectType($mime);
        $disk = self::diskName();
        $folder = trim((string) ($options['folder'] ?? ''), '/');
        $folderSegment = $folder !== '' ? Str::slug($folder, '-') : '';
        $base = 'admin-assets/' . $type . 's' . ($folderSegment !== '' ? '/' . $folderSegment : '');
        $filename = (string) Str::uuid() . '.' . $ext;
        $storedPath = $file->storeAs($base, $filename, $disk);

        return self::create([
            'admin_id'      => $admin?->id,
            'original_name' => $file->getClientOriginalName(),
            'filename'      => $filename,
            'mime_type'     => $mime,
            'size_bytes'    => $size,
            'type'          => $type,
            'disk'          => $disk,
            'path'          => $storedPath,
            'folder'        => $folderSegment ?: null,
            'label'         => $options['label']       ?? null,
            'description'   => $options['description'] ?? null,
            'is_public'     => (bool) ($options['is_public'] ?? false),
        ]);
    }
}
