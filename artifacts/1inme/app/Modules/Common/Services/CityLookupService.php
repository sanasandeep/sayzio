<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Offline city → (latitude, longitude) lookup.
 *
 * Resolution order on each lookup:
 *   1. The seeded `cities` reference table (if present and populated).
 *   2. The bundled CSV at database/data/world-cities.csv (~142k cities,
 *      public-domain data derived from lutangar/cities.json), loaded
 *      once and cached on the file store.
 *
 * Lookups are case- and accent-insensitive on (city_name, country_code).
 */
class CityLookupService
{
    /** Per-request cache of resolved keys → [lat, lng] (or null). */
    protected array $hits = [];

    /** Lazy-loaded CSV map (only built on first miss against the DB table). */
    protected ?array $csvMap = null;

    protected ?bool $hasDbTable = null;

    protected function csvPath(): string
    {
        return database_path('data/world-cities.csv');
    }

    /**
     * Returns ['latitude' => float, 'longitude' => float] or null if no match.
     */
    public function lookup(?string $city, ?string $countryCode): ?array
    {
        if (!$city || !$countryCode || strlen($countryCode) !== 2) return null;

        $cc   = strtoupper($countryCode);
        $norm = $this->normalize($city);
        if ($norm === '') return null;
        $key = $cc . '|' . $norm;

        if (array_key_exists($key, $this->hits)) {
            return $this->hits[$key];
        }

        // 1) DB-backed reference table
        if ($this->dbTableAvailable()) {
            $row = DB::table('cities')
                ->where('country_code', $cc)
                ->where('city_normalized', $norm)
                ->first(['latitude', 'longitude']);
            if ($row) {
                return $this->hits[$key] = [
                    'latitude'  => (float) $row->latitude,
                    'longitude' => (float) $row->longitude,
                ];
            }
        }

        // 2) CSV fallback
        $map = $this->getCsvMap();
        if (isset($map[$key])) {
            [$lat, $lng] = $map[$key];
            return $this->hits[$key] = ['latitude' => $lat, 'longitude' => $lng];
        }

        return $this->hits[$key] = null;
    }

    protected function dbTableAvailable(): bool
    {
        if ($this->hasDbTable !== null) return $this->hasDbTable;
        try {
            if (!Schema::hasTable('cities')) {
                return $this->hasDbTable = false;
            }
            return $this->hasDbTable = DB::table('cities')->limit(1)->count() > 0;
        } catch (\Throwable $e) {
            return $this->hasDbTable = false;
        }
    }

    protected function getCsvMap(): array
    {
        if ($this->csvMap !== null) return $this->csvMap;

        $path = $this->csvPath();
        if (!is_file($path)) {
            Log::warning("CityLookupService: CSV not found at {$path}");
            return $this->csvMap = [];
        }

        $cacheKey = 'city_lookup_map_v1_' . filemtime($path);
        // Force the file store so the ~5 MB serialized lookup map never lands
        // in the (default) database cache table.
        try { $store = Cache::store('file'); }
        catch (\Throwable $e) { $store = Cache::store(); }

        $this->csvMap = $store->remember($cacheKey, 86400 * 30, function () use ($path) {
            return $this->buildMap($path);
        });
        return $this->csvMap;
    }

    protected function buildMap(string $path): array
    {
        $map = [];
        $fh = fopen($path, 'r');
        if (!$fh) return $map;
        fgetcsv($fh); // header
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            $cc   = strtoupper($row[0] ?? '');
            $name = $row[1] ?? '';
            $lat  = (float) ($row[2] ?? 0);
            $lng  = (float) ($row[3] ?? 0);
            if (strlen($cc) !== 2 || $name === '') continue;
            if ($lat === 0.0 && $lng === 0.0) continue;
            $key = $cc . '|' . $this->normalize($name);
            if (!isset($map[$key])) $map[$key] = [$lat, $lng];
        }
        fclose($fh);
        return $map;
    }

    protected function normalize(string $name): string
    {
        $name = trim(mb_strtolower($name, 'UTF-8'));
        if (function_exists('iconv')) {
            $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($tr !== false) $name = $tr;
        }
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name);
        return trim(strtolower($name));
    }
}
