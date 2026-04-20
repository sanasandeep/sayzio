<?php

namespace App\Modules\User\Services;

class PersonaCatalog
{
    /** @return array<int, array{slug:string,label:string,icon:string,blurb:string}> */
    public static function all(): array
    {
        return config('personas.list', []);
    }

    public static function slugs(): array
    {
        return array_column(self::all(), 'slug');
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
}
