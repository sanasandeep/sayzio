<?php

namespace App\Modules\Common\Services\Carbon;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;

/**
 * Resolves the effective carbon settings for a (workspace, link)
 * pair. Workspaces store defaults under `settings.carbon`; individual
 * biolinks override them under `settings.carbon`. Missing values
 * everywhere fall back to a hard-coded conservative profile.
 *
 * Effective shape:
 *   [
 *     'enabled'              => bool,
 *     'monthly_budget_minor' => int,    // 0 = uncapped
 *     'fallback'             => 'pause'|'partial',
 *     'badge_visible'        => bool,
 *     'currency'             => 'USD',
 *   ]
 */
class CarbonSettingsResolver
{
    public const DEFAULTS = [
        'enabled'              => false,
        'monthly_budget_minor' => 0,
        'fallback'             => 'pause',
        'badge_visible'        => true,
        'currency'             => 'USD',
    ];

    public function workspaceDefaults(Workspace $workspace): array
    {
        $raw = (array) data_get($workspace->settings ?? [], 'carbon', []);
        return $this->mergeWithDefaults($raw);
    }

    public function linkOverrides(Link $link): array
    {
        return (array) data_get($link->settings ?? [], 'carbon', []);
    }

    public function effectiveFor(Workspace $workspace, Link $link): array
    {
        $defaults = $this->workspaceDefaults($workspace);
        $overrides = $this->linkOverrides($link);
        return $this->mergeWithDefaults(array_replace($defaults, $overrides));
    }

    public function isEnabledForLink(Link $link): bool
    {
        $ws = Workspace::find($link->workspace_id);
        if (!$ws) return false;
        return (bool) ($this->effectiveFor($ws, $link)['enabled'] ?? false);
    }

    public function badgeVisibleForLink(Link $link): bool
    {
        $ws = Workspace::find($link->workspace_id);
        if (!$ws) return false;
        $eff = $this->effectiveFor($ws, $link);
        return (bool) ($eff['enabled'] ?? false) && (bool) ($eff['badge_visible'] ?? true);
    }

    private function mergeWithDefaults(array $in): array
    {
        $out = self::DEFAULTS;
        foreach (self::DEFAULTS as $k => $v) {
            if (!array_key_exists($k, $in)) continue;
            $out[$k] = match ($k) {
                'enabled', 'badge_visible'     => (bool) $in[$k],
                'monthly_budget_minor'         => max(0, (int) $in[$k]),
                'fallback'                     => in_array($in[$k], ['pause', 'partial'], true) ? $in[$k] : 'pause',
                default                        => $in[$k],
            };
        }
        return $out;
    }
}
