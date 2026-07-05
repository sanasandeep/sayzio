<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Models\EventCategory;
use Illuminate\Support\Facades\Cache;

/**
 * Event category lookups (Task #3615, made admin-managed in Task #3654).
 * `settings['event_category']` used to be a free-text field, which
 * fragmented the /events directory ("music" vs "Music" vs "live music") and
 * forced the directory to guess a display icon from keywords. Categories are
 * now picked from an admin-managed list (see `EventCategory`, curated
 * originally from DEFAULTS below), so the stored value is a stable slug, the
 * directory's icon/label lookup is exact, and near-duplicate categories no
 * longer split the browse row.
 *
 * Legacy free-text values already saved on events are not broken: unknown
 * slugs fall back to keyword-based icon guessing and a humanized label, and
 * the editor exposes an "Other" option that preserves any custom value.
 */
class EventCategories
{
    /** Sentinel value the editor's "Other" select option submits. */
    public const OTHER = '__other__';

    /**
     * Original curated categories: stable slug => [label, Font Awesome icon,
     * color]. Only used to seed the `event_categories` table on install —
     * runtime lookups go through `all()` which reads the admin-managed
     * table. Kept here (rather than in the migration) so nothing is lost if
     * the table is ever re-seeded from scratch.
     */
    public const DEFAULTS = [
        'music'          => ['label' => 'Music',              'icon' => 'fa-music',              'color' => ['#3d6bff', '#5f8dff']],
        'nightlife'      => ['label' => 'Nightlife & Parties', 'icon' => 'fa-champagne-glasses',  'color' => ['#2342c7', '#3d6bff']],
        'arts'           => ['label' => 'Arts & Culture',     'icon' => 'fa-palette',             'color' => ['#0891b2', '#3d6bff']],
        'film'           => ['label' => 'Film & Media',       'icon' => 'fa-film',                'color' => ['#1e293b', '#3d6bff']],
        'comedy'         => ['label' => 'Comedy',             'icon' => 'fa-face-laugh',          'color' => ['#f59e0b', '#3d6bff']],
        'food_drink'     => ['label' => 'Food & Drink',       'icon' => 'fa-utensils',            'color' => ['#f97316', '#3d6bff']],
        'technology'     => ['label' => 'Technology',         'icon' => 'fa-microchip',           'color' => ['#0ea5e9', '#3d6bff']],
        'business'       => ['label' => 'Business & Networking', 'icon' => 'fa-briefcase',        'color' => ['#334155', '#3d6bff']],
        'education'      => ['label' => 'Education & Workshops', 'icon' => 'fa-graduation-cap',   'color' => ['#0d9488', '#3d6bff']],
        'community'      => ['label' => 'Community & Meetups', 'icon' => 'fa-people-group',       'color' => ['#3d6bff', '#0ea5e9']],
        'sports_fitness' => ['label' => 'Sports & Fitness',   'icon' => 'fa-basketball',           'color' => ['#16a34a', '#3d6bff']],
        'health_wellness'=> ['label' => 'Health & Wellness',  'icon' => 'fa-heart-pulse',          'color' => ['#ef4444', '#3d6bff']],
        'outdoor_travel' => ['label' => 'Outdoor & Travel',   'icon' => 'fa-mountain',             'color' => ['#15803d', '#0ea5e9']],
        'gaming'         => ['label' => 'Gaming & Esports',   'icon' => 'fa-gamepad',              'color' => ['#3d6bff', '#1e293b']],
        'fashion'        => ['label' => 'Fashion & Style',    'icon' => 'fa-shirt',                'color' => ['#db2777', '#3d6bff']],
        'charity'        => ['label' => 'Charity & Causes',   'icon' => 'fa-hand-holding-heart',   'color' => ['#dc2626', '#3d6bff']],
    ];

    /** Fallback gradient stops for legacy/unrecognized categories. */
    private const FALLBACK_COLOR = ['#3d6bff', '#2342c7'];

    /**
     * Keyword fallback for legacy free-text categories so old events keep a
     * sensible icon even though they were never picked from the curated list.
     */
    private const LEGACY_KEYWORD_ICONS = [
        'fa-music' => ['music', 'concert', 'dj', 'band'],
        'fa-microchip' => ['tech', 'technology', 'startup', 'coding', 'developer', 'software'],
        'fa-palette' => ['art', 'design', 'craft', 'painting'],
        'fa-utensils' => ['food', 'drink', 'dining', 'restaurant', 'wine', 'beer', 'culinary'],
        'fa-basketball' => ['sport', 'fitness', 'run', 'yoga', 'gym'],
        'fa-briefcase' => ['business', 'networking', 'conference', 'summit', 'career'],
        'fa-heart-pulse' => ['health', 'wellness', 'meditation'],
        'fa-graduation-cap' => ['education', 'workshop', 'class', 'seminar', 'training'],
        'fa-people-group' => ['community', 'social', 'meetup', 'club'],
        'fa-face-laugh' => ['comedy', 'standup'],
        'fa-film' => ['film', 'movie', 'cinema', 'screening'],
        'fa-gamepad' => ['gaming', 'esports'],
        'fa-hand-holding-heart' => ['charity', 'fundraiser', 'nonprofit'],
        'fa-mountain' => ['outdoor', 'travel', 'hiking', 'adventure'],
        'fa-shirt' => ['fashion', 'style'],
        'fa-champagne-glasses' => ['party', 'nightlife', 'festival', 'celebration'],
    ];

    public const FALLBACK_ICON = 'fa-calendar-star';

    private const CACHE_KEY = 'event_categories.enabled';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Enabled admin categories, ordered by their admin sort order, keyed by
     * slug => ['label' => ..., 'icon' => ..., 'color' => [from, to]].
     * Cached briefly since this is read on every /events request and every
     * event editor page load.
     *
     * @return array<string, array{label:string, icon:string, color:array{0:string,1:string}}>
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            if (!\Illuminate\Support\Facades\Schema::hasTable('event_categories')) {
                return self::DEFAULTS;
            }

            return EventCategory::query()
                ->enabled()
                ->ordered()
                ->get()
                ->mapWithKeys(fn (EventCategory $c) => [
                    $c->slug => [
                        'label' => $c->name,
                        'icon'  => $c->icon,
                        'color' => [$c->color_from, $c->color_to],
                    ],
                ])
                ->all();
        });
    }

    /** Forget the cached category map — call after any admin CRUD write. */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Is this an exact, currently-enabled admin category slug? */
    public static function isKnown(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    /**
     * Font Awesome icon for a stored category value. Exact lookup for
     * admin-managed slugs; keyword-guess fallback for legacy free-text;
     * calendar-star for anything unrecognized.
     */
    public static function icon(string $category): string
    {
        $known = self::all();
        if (isset($known[$category])) {
            return $known[$category]['icon'];
        }

        $c = mb_strtolower($category);
        foreach (self::LEGACY_KEYWORD_ICONS as $icon => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($c, $kw)) {
                    return $icon;
                }
            }
        }

        return self::FALLBACK_ICON;
    }

    /**
     * Human-readable label for a stored category value. Admin-managed slugs
     * use their configured name; legacy free-text is humanized (underscores
     * → spaces, title case).
     */
    public static function label(string $category): string
    {
        $known = self::all();
        if (isset($known[$category])) {
            return $known[$category]['label'];
        }

        return \Illuminate\Support\Str::title(str_replace('_', ' ', $category));
    }

    /** slug => label, for building the editor's <select>. */
    public static function selectOptions(): array
    {
        return array_map(fn ($c) => $c['label'], self::all());
    }

    /**
     * Two-stop hex gradient for a category's directory tile. Admin-managed
     * categories use their configured pair; anything else gets the default
     * blue pair (always blue-forward, never purple, per brand guard).
     *
     * @return array{0:string,1:string}
     */
    public static function colorStops(string $category): array
    {
        return self::all()[$category]['color'] ?? self::FALLBACK_COLOR;
    }

    /** CSS `linear-gradient(...)` string for a category's directory tile. */
    public static function gradient(string $category): string
    {
        [$from, $to] = self::colorStops($category);
        return "linear-gradient(135deg, {$from} 0%, {$to} 100%)";
    }

    /**
     * Normalize a legacy free-text `event_category` value onto the closest
     * admin-managed slug, or null when it can't be confidently mapped.
     * Reuses the same keyword map as icon(): if a legacy value resolves to a
     * known category's icon, it belongs in that category. Returns null for
     * values that are already a known slug (nothing to change), are
     * empty/"Other", or don't match any keyword (genuinely custom — left
     * untouched).
     *
     * Used by the one-time normalization migration so old events group under
     * the curated categories in the /events directory.
     */
    public static function slugForLegacy(string $value): ?string
    {
        $value = trim($value);
        $known = self::all();
        if ($value === '' || $value === self::OTHER || isset($known[$value])) {
            return null;
        }

        $icon = self::icon($value);
        if ($icon === self::FALLBACK_ICON) {
            return null;
        }

        foreach ($known as $slug => $meta) {
            if ($meta['icon'] === $icon) {
                return $slug;
            }
        }

        return null;
    }
}
