<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UserFile extends Model
{
    protected $fillable = [
        'user_id', 'original_name', 'filename', 'mime_type',
        'size_bytes', 'type', 'disk', 'path',
    ];

    protected $appends = ['url', 'size_human'];

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
        if ($this->disk === 's3') {
            return Storage::disk('s3')->url($this->path);
        }
        return Storage::disk('public')->url($this->path);
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
        Storage::disk($this->disk)->delete($this->path);
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
}
