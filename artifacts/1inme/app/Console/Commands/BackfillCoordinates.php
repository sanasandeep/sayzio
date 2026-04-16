<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\CityLookupService;
use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\PageSession;
use Illuminate\Console\Command;

/**
 * Walks link_clicks + page_sessions rows that don't yet have lat/lng
 * and fills them in using the bundled offline cities database
 * (matching on city + country_code). Idempotent and resumable.
 */
class BackfillCoordinates extends Command
{
    protected $signature = 'analytics:backfill-coords
        {--chunk=500 : Rows processed per batch}
        {--limit=0 : Optional cap on rows per table (0 = no cap)}
        {--no-geoip : Skip the cached-GeoIP fallback for unmatched rows}';

    protected $description = 'Backfill missing latitude/longitude on link_clicks and page_sessions: looks up the bundled cities DB first, then falls back to cached GeoIP (by IP) for any leftover rows.';

    public function handle(CityLookupService $cities, GeoIpService $geo): int
    {
        $chunk    = max(50, (int) $this->option('chunk'));
        $limit    = max(0, (int) $this->option('limit'));
        $useGeoip = !$this->option('no-geoip');

        // (city, country_code) -> [lat, lng] cache for this run
        $cache = [];
        // ip -> [lat, lng]|null cache for this run
        $ipCache = [];

        $resolve = function (?string $city, ?string $cc, ?string $ip)
            use ($cities, $geo, $useGeoip, &$cache, &$ipCache): ?array {
            if ($city && $cc) {
                $key = strtoupper($cc) . '|' . strtolower($city);
                if (!array_key_exists($key, $cache)) {
                    $cache[$key] = $cities->lookup($city, $cc);
                }
                if ($cache[$key]) return $cache[$key];
            }
            // Fallback: cached GeoIP lookup using the row's IP
            if ($useGeoip && $ip) {
                if (!array_key_exists($ip, $ipCache)) {
                    $ipCache[$ip] = $geo->detectCoordinates($ip);
                }
                if ($ipCache[$ip]) return $ipCache[$ip];
            }
            return null;
        };

        foreach ([
            ['model' => LinkClick::class,   'label' => 'link_clicks'],
            ['model' => PageSession::class, 'label' => 'page_sessions'],
        ] as $target) {
            $modelClass = $target['model'];
            $label      = $target['label'];

            $total = $modelClass::query()
                ->whereNotNull('city')->whereNotNull('country_code')
                ->where(function ($q) {
                    $q->whereNull('latitude')->orWhereNull('longitude');
                })
                ->count();

            if ($total === 0) {
                $this->line("✓ {$label}: nothing to backfill");
                continue;
            }

            $cap = $limit > 0 ? min($total, $limit) : $total;
            $this->info("• {$label}: {$cap} rows to process (chunk={$chunk})");
            $bar = $this->output->createProgressBar($cap);
            $bar->start();

            $processed = 0; $matched = 0; $unmatched = 0;
            // Walk EVERY row missing coordinates — even those where city or
            // country_code is null — so the GeoIP fallback can recover them.
            $modelClass::query()
                ->where(function ($q) {
                    $q->whereNull('latitude')->orWhereNull('longitude');
                })
                ->select('id', 'city', 'country_code', 'ip_address')
                ->orderBy('id')
                ->chunkById($chunk, function ($rows) use (&$processed, &$matched, &$unmatched, $resolve, $modelClass, $bar, $cap) {
                    $byCoords = [];
                    foreach ($rows as $row) {
                        $coords = $resolve($row->city, $row->country_code, $row->ip_address);
                        if ($coords) {
                            $key = $coords['latitude'] . ':' . $coords['longitude'];
                            $byCoords[$key]['lat'] = $coords['latitude'];
                            $byCoords[$key]['lng'] = $coords['longitude'];
                            $byCoords[$key]['ids'][] = $row->id;
                            $matched++;
                        } else {
                            $unmatched++;
                        }
                        $processed++;
                        $bar->advance();
                        if ($processed >= $cap) break;
                    }
                    foreach ($byCoords as $bucket) {
                        $modelClass::whereIn('id', $bucket['ids'])->update([
                            'latitude'  => $bucket['lat'],
                            'longitude' => $bucket['lng'],
                        ]);
                    }
                    return $processed < $cap;
                });

            $bar->finish();
            $this->newLine();
            $this->line("  matched: {$matched}, unmatched: {$unmatched}");
        }

        $this->info('Backfill complete.');
        return self::SUCCESS;
    }
}
