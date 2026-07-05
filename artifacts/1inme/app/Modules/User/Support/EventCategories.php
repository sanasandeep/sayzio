<?php

namespace App\Modules\User\Support;

/**
 * Curated event category list (Task #3615). `settings['event_category']` used
 * to be a free-text field, which fragmented the /events directory ("music" vs
 * "Music" vs "live music") and forced the directory to guess a display icon
 * from keywords. Categories are now picked from this fixed list, so the stored
 * value is a stable slug, the directory's icon/label lookup is exact, and
 * near-duplicate categories no longer split the browse row.
 *
 * Legacy free-text values already saved on events are not broken: unknown
 * slugs fall back to keyword-based icon guessing and a humanized label, and the
 * editor exposes an "Other" option that preserves any custom value.
 */
class EventCategories
{
    /** Sentinel value the editor's "Other" select option submits. */
    public const OTHER = '__other__';

    /**
     * Curated categories: stable slug => [label, Font Awesome icon]. The slug
     * is what gets stored/filtered on; the label/icon drive the directory UI.
     */
    public const CATEGORIES = [
        'music'          => ['label' => 'Music',              'icon' => 'fa-music'],
        'nightlife'      => ['label' => 'Nightlife & Parties', 'icon' => 'fa-champagne-glasses'],
        'arts'           => ['label' => 'Arts & Culture',     'icon' => 'fa-palette'],
        'film'           => ['label' => 'Film & Media',       'icon' => 'fa-film'],
        'comedy'         => ['label' => 'Comedy',             'icon' => 'fa-face-laugh'],
        'food_drink'     => ['label' => 'Food & Drink',       'icon' => 'fa-utensils'],
        'technology'     => ['label' => 'Technology',         'icon' => 'fa-microchip'],
        'business'       => ['label' => 'Business & Networking', 'icon' => 'fa-briefcase'],
        'education'      => ['label' => 'Education & Workshops', 'icon' => 'fa-graduation-cap'],
        'community'      => ['label' => 'Community & Meetups', 'icon' => 'fa-people-group'],
        'sports_fitness' => ['label' => 'Sports & Fitness',   'icon' => 'fa-basketball'],
        'health_wellness'=> ['label' => 'Health & Wellness',  'icon' => 'fa-heart-pulse'],
        'outdoor_travel' => ['label' => 'Outdoor & Travel',   'icon' => 'fa-mountain'],
        'gaming'         => ['label' => 'Gaming & Esports',   'icon' => 'fa-gamepad'],
        'fashion'        => ['label' => 'Fashion & Style',    'icon' => 'fa-shirt'],
        'charity'        => ['label' => 'Charity & Causes',   'icon' => 'fa-hand-holding-heart'],
    ];

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

    /** Is this an exact curated slug? */
    public static function isKnown(string $slug): bool
    {
        return isset(self::CATEGORIES[$slug]);
    }

    /**
     * Font Awesome icon for a stored category value. Exact lookup for curated
     * slugs; keyword-guess fallback for legacy free-text; calendar-star for
     * anything unrecognized.
     */
    public static function icon(string $category): string
    {
        if (isset(self::CATEGORIES[$category])) {
            return self::CATEGORIES[$category]['icon'];
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
     * Human-readable label for a stored category value. Curated slugs use their
     * curated label; legacy free-text is humanized (underscores → spaces, title
     * case).
     */
    public static function label(string $category): string
    {
        if (isset(self::CATEGORIES[$category])) {
            return self::CATEGORIES[$category]['label'];
        }

        return \Illuminate\Support\Str::title(str_replace('_', ' ', $category));
    }

    /** slug => label, for building the editor's <select>. */
    public static function selectOptions(): array
    {
        return array_map(fn ($c) => $c['label'], self::CATEGORIES);
    }
}
