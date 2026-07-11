<?php

namespace App\Modules\Admin\Models;

use Database\Seeders\OnboardingSlidesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OnboardingSlide extends Model
{
    /**
     * The wording fields that must stay in sync with the bundled default.
     * These are the strings a user actually reads in the intro carousel, so
     * drift here means the same user could see different copy depending on
     * whether the admin has edited the live row.
     */
    public const COPY_FIELDS = ['category', 'title', 'body'];

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
     * The seeded default copy for every slug, keyed by slug. Reads straight
     * from the seeder so there is exactly one source of truth for the
     * shipped wording.
     *
     * @return array<string, array{slug:string, sort_order:int, category:string, title:string, body:string}>
     */
    public static function seededDefaults(): array
    {
        $bySlug = [];
        foreach (OnboardingSlidesSeeder::defaults() as $row) {
            $bySlug[$row['slug']] = $row;
        }
        return $bySlug;
    }

    /**
     * The seeded default row for THIS slide's slug, or null when the slide
     * was created by an admin and has no shipped default to compare against.
     *
     * @return array{slug:string, sort_order:int, category:string, title:string, body:string}|null
     */
    public function seededDefault(): ?array
    {
        return static::seededDefaults()[$this->slug] ?? null;
    }

    /**
     * The copy fields whose live value no longer matches the seeded default.
     * Empty when the slide matches the default (or has no default).
     *
     * @return array<int, string>
     */
    public function driftedFields(): array
    {
        $default = $this->seededDefault();
        if ($default === null) {
            return [];
        }

        $drifted = [];
        foreach (self::COPY_FIELDS as $field) {
            if ((string) ($this->{$field} ?? '') !== (string) ($default[$field] ?? '')) {
                $drifted[] = $field;
            }
        }
        return $drifted;
    }

    /**
     * One-word classification used by the admin UI:
     *   - 'custom'     — admin-created slug with no shipped default
     *   - 'customized' — matches a shipped slug but the copy was edited
     *   - 'default'    — copy still matches the shipped default exactly
     */
    public function customizationState(): string
    {
        if ($this->seededDefault() === null) {
            return 'custom';
        }
        return empty($this->driftedFields()) ? 'default' : 'customized';
    }

    /**
     * Whether this slide's copy has drifted from its shipped default. Slides
     * with no default (admin-created) are not considered drifted.
     */
    public function hasDriftedFromDefault(): bool
    {
        return $this->customizationState() === 'customized';
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
