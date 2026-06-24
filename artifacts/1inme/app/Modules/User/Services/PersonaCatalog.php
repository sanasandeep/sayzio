<?php

namespace App\Modules\User\Services;

class PersonaCatalog
{
    /** @return array<int, array{slug:string,label:string,icon:string,blurb:string,image?:string,group?:string}> */
    public static function all(): array
    {
        return config('personas.list', []);
    }

    public static function slugs(): array
    {
        return array_column(self::all(), 'slug');
    }

    /**
     * Persona slug => label, suitable for dropdowns. Keeps insertion
     * order so the admin "category" picker matches the onboarding order.
     *
     * @return array<string,string>
     */
    public static function slugLabelMap(): array
    {
        $out = [];
        foreach (self::all() as $p) {
            $out[$p['slug']] = $p['label'];
        }
        return $out;
    }

    /**
     * Personas grouped by their `group` key for the picker UI section
     * headers. Personas with no group are bucketed under "Other".
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::all() as $p) {
            $g = $p['group'] ?? 'Other';
            $groups[$g][] = $p;
        }
        return $groups;
    }

    public static function labelFor(?string $slug): ?string
    {
        if (!$slug) return null;
        foreach (self::all() as $p) {
            if ($p['slug'] === $slug) return $p['label'];
        }
        return null;
    }

    public static function isValid(?string $slug): bool
    {
        return $slug !== null && in_array($slug, self::slugs(), true);
    }

    /**
     * Ordered persona groups for the biolink wizard's Step 1 (the "category"
     * step). Each entry carries a FontAwesome icon + short blurb so the wizard
     * renders the same icon-led tiles as the old hand-written category list.
     * The order here is the canonical display order; `personasInGroup()` lists
     * the matching personas for Step 2.
     *
     * @return array<int, array{key:string,label:string,icon:string,blurb:string}>
     */
    public static function groups(): array
    {
        $meta = [
            'Creators'  => ['icon' => 'fa-star',                 'blurb' => 'Content, fans, and all your links in one place.'],
            'Business'  => ['icon' => 'fa-briefcase',            'blurb' => 'Shops, brands, agencies and the things you sell.'],
            'Music'     => ['icon' => 'fa-music',                'blurb' => 'Streams, gigs, tour dates, mixes and merch.'],
            'Food'      => ['icon' => 'fa-utensils',             'blurb' => 'Restaurants, cafes, chefs and food creators.'],
            'Wellness'  => ['icon' => 'fa-heart-pulse',          'blurb' => 'Fitness, yoga, therapy and nutrition.'],
            'Local'     => ['icon' => 'fa-location-dot',         'blurb' => 'Local spots, venues, hospitality and events.'],
            'Services'  => ['icon' => 'fa-user-tie',             'blurb' => 'Coaches, consultants, freelancers and pros.'],
            'Community' => ['icon' => 'fa-people-group',          'blurb' => 'Nonprofits, faith groups, clubs and causes.'],
            'Lifestyle' => ['icon' => 'fa-wand-magic-sparkles',  'blurb' => 'Fashion, beauty, models and travel.'],
            'Other'     => ['icon' => 'fa-circle-question',      'blurb' => 'Anything else — start from a general page.'],
        ];

        // Only surface groups that actually have personas behind them.
        $present = [];
        foreach (self::all() as $p) {
            $present[$p['group'] ?? 'Other'] = true;
        }

        $out = [];
        foreach ($meta as $key => $info) {
            if (!isset($present[$key])) {
                continue;
            }
            $out[] = [
                'key'   => $key,
                'label' => $key,
                'icon'  => $info['icon'],
                'blurb' => $info['blurb'],
            ];
        }

        // Defensive: surface any group present in config but missing from the
        // ordered meta map above, so a newly-added group is never dropped.
        foreach (array_keys($present) as $key) {
            if (!collect($out)->contains('key', $key)) {
                $out[] = ['key' => $key, 'label' => $key, 'icon' => 'fa-layer-group', 'blurb' => ''];
            }
        }

        return $out;
    }

    /** Valid group keys (display order). @return list<string> */
    public static function groupKeys(): array
    {
        return array_column(self::groups(), 'key');
    }

    public static function isValidGroup(?string $key): bool
    {
        return $key !== null && in_array($key, self::groupKeys(), true);
    }

    /**
     * Personas in a given group, in catalog (insertion) order.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function personasInGroup(string $group): array
    {
        return array_values(array_filter(
            self::all(),
            static fn ($p) => ($p['group'] ?? 'Other') === $group,
        ));
    }

    /** The group a persona belongs to (defaults to "Other"). */
    public static function groupOf(?string $slug): ?string
    {
        if (!$slug) return null;
        foreach (self::all() as $p) {
            if ($p['slug'] === $slug) {
                return $p['group'] ?? 'Other';
            }
        }
        return null;
    }

    /**
     * Compatibility map: resolve a persona slug to the legacy wizard
     * (category, page_type) combo that drives BiolinkWizardQuestions and
     * BiolinkPageRecipes. This is the bridge that lets PersonaCatalog be the
     * single taxonomy source while the deterministic recipe engine keeps
     * working against its existing keys. `industry` is always null here — the
     * optional niche refinement is chosen separately in the wizard and layered
     * back on.
     *
     * Falls back to a sensible per-group default, then a global default, so a
     * newly-added persona never breaks deterministic generation even before a
     * bespoke mapping is added.
     *
     * @return array{category:string,page_type:string,industry:null}
     */
    public static function wizardResolution(?string $slug): array
    {
        // Explicit per-persona mapping onto the legacy combos.
        $map = [
            // Creators
            'creator'      => ['creator', 'influencer'],
            'influencer'   => ['creator', 'influencer'],
            'artist'       => ['creator', 'artist'],
            'writer'       => ['creator', 'writer'],
            'author'       => ['creator', 'writer'],
            'journalist'   => ['creator', 'writer'],
            'podcaster'    => ['creator', 'podcaster'],
            'youtuber'     => ['creator', 'youtuber'],
            'streamer'     => ['creator', 'youtuber'],
            'developer'    => ['personal', 'developer'],
            'photographer' => ['photographer', 'photographer'],
            'filmmaker'    => ['photographer', 'videographer'],
            // Business
            'business'     => ['business', 'local_shop'],
            'agency'       => ['business', 'agency'],
            // Music
            'musician'     => ['musician', 'solo_artist'],
            'dj'           => ['musician', 'dj'],
            'band'         => ['musician', 'band'],
            // Food
            'chef'         => ['restaurant', 'restaurant'],
            // Wellness
            'fitness'      => ['health_wellness', 'fitness_trainer'],
            'trainer'      => ['health_wellness', 'fitness_trainer'],
            'yoga'         => ['health_wellness', 'yoga'],
            'nutritionist' => ['health_wellness', 'nutritionist'],
            'therapist'    => ['health_wellness', 'therapist'],
            // Local
            'restaurant'   => ['restaurant', 'restaurant'],
            'cafe'         => ['restaurant', 'cafe'],
            'event'        => ['event', 'wedding'],
            // Services
            'coach'        => ['coach', 'life'],
            'consultant'   => ['coach', 'business'],
            'realestate'   => ['real_estate', 'residential'],
            'freelancer'   => ['personal', 'professional'],
            'tattoo'       => ['fashion_beauty', 'salon'],
            'barber'       => ['fashion_beauty', 'salon'],
            // Community
            'nonprofit'    => ['nonprofit', 'charity'],
            'church'       => ['faith', 'church'],
            'community'    => ['faith', 'community_group'],
            // Lifestyle
            'model'        => ['fashion_beauty', 'model'],
            'beauty'       => ['fashion_beauty', 'beauty_artist'],
            'fashion'      => ['fashion_beauty', 'fashion_brand'],
            'travel'       => ['travel_creator', 'travel_blogger'],
            // Other
            'student'      => ['personal', 'student'],
            'other'        => ['personal', 'professional'],
        ];

        if ($slug !== null && isset($map[$slug])) {
            return ['category' => $map[$slug][0], 'page_type' => $map[$slug][1], 'industry' => null];
        }

        // Per-group fallback so a new persona resolves to its group's default.
        $groupDefaults = [
            'Creators'  => ['creator', 'influencer'],
            'Business'  => ['business', 'local_shop'],
            'Music'     => ['musician', 'solo_artist'],
            'Food'      => ['restaurant', 'restaurant'],
            'Wellness'  => ['health_wellness', 'fitness_trainer'],
            'Local'     => ['business', 'local_shop'],
            'Services'  => ['personal', 'professional'],
            'Community' => ['nonprofit', 'charity'],
            'Lifestyle' => ['fashion_beauty', 'fashion_brand'],
            'Other'     => ['personal', 'professional'],
        ];

        $group = self::groupOf($slug);
        if ($group !== null && isset($groupDefaults[$group])) {
            return ['category' => $groupDefaults[$group][0], 'page_type' => $groupDefaults[$group][1], 'industry' => null];
        }

        // Global default — a general-purpose personal page.
        return ['category' => 'personal', 'page_type' => 'professional', 'industry' => null];
    }

    /**
     * Returns a human-friendly noun phrase describing the persona, suitable
     * for sentences like "Recommended for {pluralLabelFor('writer')}" →
     * "Recommended for writers". Avoids forced plural-s on labels that
     * already are noun phrases (e.g. "Coach/Educator", "Something else").
     */
    public static function pluralLabelFor(?string $slug): ?string
    {
        $label = self::labelFor($slug);
        if (!$label) return null;
        // Multi-word or punctuated labels read awkwardly with a trailing "s".
        if (preg_match('/[\s\/\-]/', $label)) return $label;
        // Already plural / mass nouns
        if (preg_match('/(s|y)$/i', $label)) return $label;
        return $label . 's';
    }
}
