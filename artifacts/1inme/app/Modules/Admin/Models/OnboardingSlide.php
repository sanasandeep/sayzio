<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OnboardingSlide extends Model
{
    protected $fillable = [
        'slug', 'category', 'title', 'body', 'image_path',
        'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Absolute URL for the slide image. Uses Storage::url() so the
     * `public` disk's mount point (`/storage/...` by default) is
     * respected, then turned absolute for the mobile client.
     */
    public function imageUrl(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }
        $rel = Storage::disk('public')->url($this->image_path);
        if (str_starts_with($rel, 'http://') || str_starts_with($rel, 'https://')) {
            return $rel;
        }
        return rtrim(config('app.url'), '/') . $rel;
    }
}
