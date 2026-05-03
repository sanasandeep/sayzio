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
