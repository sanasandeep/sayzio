<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class FileLink extends Model
{
    protected $fillable = [
        'link_id', 'original_name', 'stored_path',
        'mime_type', 'file_size', 'download_count',
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
}
