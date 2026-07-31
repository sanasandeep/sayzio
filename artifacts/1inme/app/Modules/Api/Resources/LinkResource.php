<?php

namespace App\Modules\Api\Resources;

use App\Modules\User\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LinkResource
{
    /**
     * Per-process schema-presence flags. Schema::hasTable/hasColumn results
     * are already cached by Laravel's SchemaBuilder, but storing the result
     * in a static bool avoids the method-call overhead on every toArray() call
     * when serialising batches of links (e.g. dashboard recent-links list).
     */
    private static ?bool $pixelTableExists    = null;
    private static ?bool $autoPixelColExists  = null;

    /**
     * Per-request pixel-fire cache populated by preload().
     * Key = link_id (int), value = ['count' => int, 'providers' => string[]].
     * A null value signals "not preloaded yet" (fall through to per-link query).
     *
     * @var array<int, array{count:int, providers:string[]}|null>
     */
    private static array $pixelFireCache = [];

    /**
     * Batch-preload pixel-fire data for a list of links in a single query,
     * eliminating the per-link N+1 when toArray() is called for multiple
     * links in the same request (e.g. dashboard recent-links + top-link).
     *
     * Call this BEFORE looping through toArray() on any batch of links.
     * Safe to call with an empty array or repeatedly; already-preloaded IDs
     * are skipped so overlapping batches don't double-fetch.
     *
     * @param Link[] $links
     */
    public static function preload(array $links): void
    {
        if (empty($links)) {
            return;
        }

        // Resolve schema flags once per process.
        if (self::$pixelTableExists === null) {
            self::$pixelTableExists   = Schema::hasTable('link_pixel_fires');
            self::$autoPixelColExists = Schema::hasColumn('links', 'auto_pixel');
        }

        if (!self::$pixelTableExists) {
            return;
        }

        // Only fetch IDs not already in the cache.
        $ids = array_values(array_unique(array_map(fn (Link $l) => (int) $l->id, $links)));
        $missing = array_filter($ids, fn (int $id) => !array_key_exists($id, self::$pixelFireCache));
        if (empty($missing)) {
            return;
        }

        // Seed with empty data so toArray() never falls back to per-link queries
        // for these IDs, even when no pixel-fire rows exist.
        foreach ($missing as $id) {
            self::$pixelFireCache[$id] = ['count' => 0, 'providers' => []];
        }

        try {
            $rows = DB::table('link_pixel_fires')
                ->whereIn('link_id', $missing)
                ->select('link_id', 'providers')
                ->get();

            $providerSets = [];
            foreach ($rows as $r) {
                $lid = (int) $r->link_id;
                self::$pixelFireCache[$lid]['count']++;
                foreach (explode(',', (string) $r->providers) as $p) {
                    $p = trim($p);
                    if ($p !== '') {
                        $providerSets[$lid][$p] = true;
                    }
                }
            }
            foreach ($providerSets as $lid => $set) {
                $providers = array_keys($set);
                sort($providers);
                self::$pixelFireCache[$lid]['providers'] = $providers;
            }
        } catch (\Throwable) {
            // Preload is best-effort; toArray() will fall back to per-link queries.
        }
    }

    public static function toArray(Link $l): array
    {
        $autoPixel          = false;
        $pixelFiresCount    = 0;
        $pixelFiresProviders = [];

        try {
            // Resolve schema flags once per process.
            if (self::$pixelTableExists === null) {
                self::$pixelTableExists   = Schema::hasTable('link_pixel_fires');
                self::$autoPixelColExists = Schema::hasColumn('links', 'auto_pixel');
            }

            if (self::$autoPixelColExists) {
                $autoPixel = (bool) ($l->auto_pixel ?? false);
            }

            $lid = (int) $l->id;

            if (array_key_exists($lid, self::$pixelFireCache)) {
                // Preloaded via preload() — zero extra queries.
                $pixelFiresCount    = self::$pixelFireCache[$lid]['count'];
                $pixelFiresProviders = self::$pixelFireCache[$lid]['providers'];
            } elseif (self::$pixelTableExists) {
                // Fallback: per-link queries for links not covered by preload().
                $pixelFiresCount = (int) DB::table('link_pixel_fires')
                    ->where('link_id', $lid)->count();
                $rows = DB::table('link_pixel_fires')
                    ->where('link_id', $lid)
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
        } catch (\Throwable) {
            // best-effort
        }

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
            // Folder (project) the link lives in — used by clients to tint
            // grid tiles with the folder colour. Null when not in a folder.
            'project'         => $l->project ? [
                'id'    => $l->project->id,
                'name'  => $l->project->name,
                'color' => $l->project->color,
            ] : null,
            'short_url'       => $l->getShortUrl(),
            'auto_pixel'      => $autoPixel,
            'pixel_fires'     => [
                'count'     => $pixelFiresCount,
                'providers' => $pixelFiresProviders,
            ],
            'design_locked'   => $l->isDesignLocked(),
            'design_lock'     => $l->isDesignLocked() ? [
                'template_id'   => $l->designLockInfo()['template_id'] ?? null,
                'template_name' => $l->designLockInfo()['template_name'] ?? null,
                'locked_at'     => $l->designLockInfo()['locked_at'] ?? null,
            ] : null,
            'is_smart'        => !empty($rules),
            'smart_rules_count' => count($rules),
            'settings'        => empty($settings) ? new \stdClass() : $settings,
            'created_at'      => optional($l->created_at)->toIso8601String(),
            'updated_at'      => optional($l->updated_at)->toIso8601String(),
        ];
    }
}
