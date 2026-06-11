<?php

namespace App\Modules\Api\Resources;

use App\Modules\User\Models\Link;

class LinkResource
{
    public static function toArray(Link $l): array
    {
        // Auto-pixel + retargeting fire stats. Values are best-effort and
        // gracefully degrade when the table doesn't exist yet (e.g. on a
        // pre-migration deploy serving an older client).
        $autoPixel = false;
        $pixelFiresCount = 0;
        $pixelFiresProviders = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'auto_pixel')) {
                $autoPixel = (bool) ($l->auto_pixel ?? false);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('link_pixel_fires')) {
                $pixelFiresCount = (int) \Illuminate\Support\Facades\DB::table('link_pixel_fires')
                    ->where('link_id', $l->id)->count();
                $rows = \Illuminate\Support\Facades\DB::table('link_pixel_fires')
                    ->where('link_id', $l->id)
                    ->select('providers')
                    ->limit(500)
                    ->get();
                $set = [];
                foreach ($rows as $r) {
                    foreach (explode(',', (string) $r->providers) as $p) {
                        $p = trim($p);
                        if ($p !== '') $set[$p] = true;
                    }
                }
                $pixelFiresProviders = array_keys($set);
                sort($pixelFiresProviders);
            }
        } catch (\Throwable $e) { /* best-effort */ }

        $settings  = $l->settings ?? [];
        $rules     = is_array($settings['smart_rules'] ?? null) ? $settings['smart_rules'] : [];
        return [
            'id'              => $l->id,
            'type'            => $l->type,
            'alias'           => $l->alias,
            'title'           => $l->title,
            'long_url'        => $l->long_url,
            'visibility'      => $l->visibility ?? 'public',
            'is_active'       => (bool) $l->is_active,
            'is_verified'     => (bool) $l->is_verified,
            'is_password_protected' => (bool) $l->is_password_protected,
            'expires_at'      => optional($l->expires_at)->toIso8601String(),
            'total_clicks'    => (int) $l->total_clicks,
            'unique_clicks'   => (int) $l->unique_clicks,
            'seo_title'       => $l->seo_title,
            'seo_description' => $l->seo_description,
            'seo_image'       => $l->seo_image,
            'domain_id'       => $l->domain_id,
            'domain'          => $l->domain?->domain,
            'short_url'       => $l->getShortUrl(),
            'auto_pixel'      => $autoPixel,
            'pixel_fires'     => [
                'count'     => $pixelFiresCount,
                'providers' => $pixelFiresProviders,
            ],
            'is_smart'        => !empty($rules),
            'smart_rules_count' => count($rules),
            'settings'        => empty($settings) ? new \stdClass() : $settings,
            'created_at'      => optional($l->created_at)->toIso8601String(),
            'updated_at'      => optional($l->updated_at)->toIso8601String(),
        ];
    }
}
