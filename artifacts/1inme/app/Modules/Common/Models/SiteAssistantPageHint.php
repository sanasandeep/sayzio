<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAssistantPageHint extends Model
{
    protected $fillable = [
        'label', 'route_pattern', 'surface', 'description',
        'suggested_actions', 'disable_widget', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'suggested_actions' => 'array',
            'is_active'         => 'bool',
            'disable_widget'    => 'bool',
            'priority'          => 'int',
        ];
    }

    /**
     * Pick the best-matching active hint for a route name + surface.
     * route_pattern uses fnmatch-style wildcards (e.g. user.links.*).
     */
    public static function resolve(?string $routeName, ?string $path, string $surface): ?self
    {
        $hints = static::where('is_active', true)
            ->whereIn('surface', [$surface, 'any'])
            ->orderBy('priority')
            ->get();

        $routeName = (string) $routeName;
        $path      = (string) $path;

        foreach ($hints as $h) {
            $p = (string) $h->route_pattern;
            if ($p === '') continue;
            if (fnmatch($p, $routeName) || fnmatch($p, $path)) {
                return $h;
            }
        }
        return null;
    }
}
