<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Offline city → (latitude, longitude) lookup backed by a bundled CSV
 * (~142k cities, public-domain data from lutangar/cities.json).
 *
 * The lookup map is built once on first use and cached for the
 * lifetime of the process / file cache. Lookups are case- and
 * accent-insensitive on (city_name, country_code).
 */
class CityLookupService
{
    protected ?array $map = null;

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

        $map = $this->getMap();
        if (!$map) return null;

        $key = strtoupper($countryCode) . '|' . $this->normalize($city);
        if (isset($map[$key])) {
            [$lat, $lng] = $map[$key];
            return ['latitude' => $lat, 'longitude' => $lng];
        }
        return null;
    }

    protected function getMap(): array
    {
        if ($this->map !== null) return $this->map;

        // Cache key includes the file mtime so a new CSV invalidates automatically.
        $path = $this->csvPath();
        if (!is_file($path)) {
            Log::warning("CityLookupService: CSV not found at {$path}");
            $this->map = [];
            return $this->map;
        }

        $cacheKey = 'city_lookup_map_v1_' . filemtime($path);
        // Force the file store so the ~5 MB serialized lookup map never lands
        // in the (default) database cache table.
        try {
            $store = Cache::store('file');
        } catch (\Throwable $e) {
            $store = Cache::store();
        }
        $this->map = $store->remember($cacheKey, 86400 * 30, function () use ($path) {
            return $this->buildMap($path);
        });
        return $this->map;
    }

    protected function buildMap(string $path): array
    {
        $map = [];
        $fh = fopen($path, 'r');
        if (!$fh) return $map;

        // Header row
        fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            $cc   = strtoupper($row[0] ?? '');
            $name = $row[1] ?? '';
            $lat  = (float) ($row[2] ?? 0);
            $lng  = (float) ($row[3] ?? 0);
            if (strlen($cc) !== 2 || $name === '') continue;
            if ($lat === 0.0 && $lng === 0.0) continue;
            $key = $cc . '|' . $this->normalize($name);
            // Keep first occurrence
            if (!isset($map[$key])) {
                $map[$key] = [$lat, $lng];
            }
        }
        fclose($fh);
        return $map;
    }

    protected function normalize(string $name): string
    {
        $name = trim($name);
        // Lowercase
        $name = mb_strtolower($name, 'UTF-8');
        // Strip accents
        if (function_exists('iconv')) {
            $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
            if ($tr !== false) $name = $tr;
        }
        // Collapse non-alnum to single space
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name);
        return trim(strtolower($name));
    }
}
