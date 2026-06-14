<?php

namespace App\Modules\User\Models;

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
}
