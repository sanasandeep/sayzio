<?php

namespace App\Modules\User\Models;

use App\Modules\Common\Services\AppLinkResolver;
use Illuminate\Database\Eloquent\Model;

class FileLink extends Model
{
    protected $fillable = [
        'link_id', 'original_name', 'stored_path',
        'mime_type', 'file_size', 'download_count', 'disk',
        'show_download_page',
    ];

    protected $casts = [
        'show_download_page' => 'boolean',
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function getHumanFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Public URL to the stored file (used by the deep-link / "open in app"
     * resolver). Returns null when the disk can't produce a URL.
     */
    public function publicUrl(): ?string
    {
        if (!$this->stored_path) return null;
        try {
            return \Illuminate\Support\Facades\Storage::disk($this->disk ?: 'public')->url($this->stored_path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Whether this file's public URL can actually resolve to a known mobile
     * app — i.e. whether enabling "open in app" could ever produce a real
     * deep-link interstitial instead of being a silent no-op.
     */
    public function canDeepLink(): bool
    {
        $url = $this->publicUrl();
        return $url ? AppLinkResolver::resolve($url) !== null : false;
    }

    /**
     * Whether files stored on the given disk could ever resolve to a known
     * app. Used by the create form to decide if the "open in app" toggle is
     * worth showing at all (before a file is uploaded we only know the disk,
     * not the final path — but the host that drives app resolution is fixed
     * by the disk's base URL, not the path).
     */
    public static function diskSupportsDeepLink(?string $disk = null): bool
    {
        try {
            $url = \Illuminate\Support\Facades\Storage::disk($disk ?: 'public')->url('__deep_link_probe__');
            return $url ? AppLinkResolver::resolve($url) !== null : false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
