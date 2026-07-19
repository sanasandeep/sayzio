<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AndroidApkRelease extends Model
{
    protected $table = 'android_apk_releases';

    protected $fillable = [
        'version_name',
        'build_number',
        'file_size_bytes',
        'disk',
        'path',
        'eas_build_id',
        'source_url',
        'published_by_admin_id',
        'notes',
        'is_live',
    ];

    protected $casts = [
        'file_size_bytes'    => 'integer',
        'is_live'            => 'boolean',
        'published_by_admin_id' => 'integer',
    ];

    protected $appends = ['size_human'];

    public function getSizeHumanAttribute(): string
    {
        return self::humanBytes((int) $this->file_size_bytes);
    }

    public static function live(): ?self
    {
        return static::where('is_live', true)->latest()->first();
    }

    /**
     * Mark this release as live; unmarks all other releases atomically.
     */
    public function setAsLive(): void
    {
        static::where('is_live', true)->update(['is_live' => false]);
        $this->update(['is_live' => true]);
    }

    /**
     * Return the disk driver name.
     */
    public function diskDriver(): string
    {
        return (string) config("filesystems.disks.{$this->disk}.driver", 'local');
    }

    /**
     * Whether this release is stored on an S3-backed disk.
     */
    public function isS3(): bool
    {
        return $this->diskDriver() === 's3';
    }

    /**
     * Generate a temporary S3 download URL (15 min TTL).
     */
    public function temporaryUrl(int $minutesTtl = 15): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutesTtl)
        );
    }

    /**
     * Absolute path on the local filesystem (only for local-driver disks).
     */
    public function localPath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public static function diskName(): string
    {
        return (string) config('filesystems.apk_disk', env('FILESYSTEM_DISK', 'public'));
    }

    public static function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        if ($bytes < 1_073_741_824) {
            return round($bytes / 1_048_576, 1) . ' MB';
        }
        return round($bytes / 1_073_741_824, 2) . ' GB';
    }
}
