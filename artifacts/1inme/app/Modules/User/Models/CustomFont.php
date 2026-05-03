<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A user-uploaded font (.woff/.woff2/.ttf/.otf) that powers the "My Fonts"
 * section pinned at the top of every font picker. Stored on the same disk
 * model as UserFile so quotas and serving stay consistent.
 */
class CustomFont extends Model
{
    protected $fillable = [
        'user_id', 'family', 'original_name', 'disk', 'path', 'format', 'size_bytes',
    ];

    protected $appends = ['url'];

    public const FORMATS = [
        'woff2' => 'woff2',
        'woff'  => 'woff',
        'ttf'   => 'truetype',
        'otf'   => 'opentype',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        $diskName = $this->disk === 'public' ? 'public' : ($this->disk === 's3' ? 's3' : 'public');
        try {
            return Storage::disk($diskName)->url($this->path);
        } catch (\Throwable $e) {
            return '/storage/' . ltrim($this->path, '/');
        }
    }

    public function deleteFile(): bool
    {
        $diskName = $this->disk === 'public' ? 'public' : ($this->disk === 's3' ? 's3' : 'public');
        try {
            Storage::disk($diskName)->delete($this->path);
        } catch (\Throwable $e) {
            // Ignore — DB row removal is the source of truth for the picker.
        }
        return $this->delete();
    }

    /** Map a file extension to a CSS format() declaration. */
    public static function detectFormat(string $extension): ?string
    {
        return self::FORMATS[strtolower($extension)] ?? null;
    }

    /**
     * The token written into settings.font_family for a custom font. We use a
     * "custom:<family>" prefix so the public renderer and sanitizer can tell
     * Google Fonts apart from a user upload without an extra DB lookup.
     */
    public function settingsToken(): string
    {
        return 'custom:' . $this->family;
    }
}
