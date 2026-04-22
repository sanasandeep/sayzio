<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OnboardingSlide extends Model
{
    protected $fillable = [
        'slug', 'category', 'title', 'body',
        'image_path', 'gallery_images',
        'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order'     => 'integer',
            'gallery_images' => 'array',
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
     * Absolute URL for a single image stored on the public disk.
     * Used both for the legacy `image_path` and each gallery item.
     */
    protected function absoluteUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }
        $rel = Storage::disk('public')->url($path);
        if (str_starts_with($rel, 'http://') || str_starts_with($rel, 'https://')) {
            return $rel;
        }
        return rtrim(config('app.url'), '/') . $rel;
    }

    public function imageUrl(): ?string
    {
        return $this->absoluteUrl($this->image_path);
    }

    /**
     * Absolute URLs for the gallery, in display order. Falls back to
     * a single-element list containing the legacy `image_path` so old
     * slides without a gallery still render an image on the mobile.
     *
     * @return array<int, string>
     */
    public function galleryUrls(): array
    {
        $paths = $this->gallery_images ?: [];
        $urls  = [];

        foreach ($paths as $p) {
            $u = $this->absoluteUrl($p);
            if ($u !== null) {
                $urls[] = $u;
            }
        }

        if (empty($urls) && $this->image_path) {
            $u = $this->absoluteUrl($this->image_path);
            if ($u !== null) {
                $urls[] = $u;
            }
        }

        return $urls;
    }
}
