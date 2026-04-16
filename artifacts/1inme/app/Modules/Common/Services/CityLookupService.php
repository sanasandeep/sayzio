<?php

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Offline city → (latitude, longitude) lookup.
 *
 * Resolution order on each lookup:
 *   1. The seeded `cities` reference table (if present and populated).
 *      This is the expected hot path in any deployed or properly
 *      seeded environment (see database/seeders/CitiesTableSeeder).
 *   2. A streaming scan of the bundled CSV at
 *      database/data/world-cities.csv (~142k rows, public-domain data
 *      derived from lutangar/cities.json). This is only used when the
 *      `cities` table is missing or empty — e.g. before the seeder has
 *      run — and is intentionally streamed row-by-row so the full file
 *      never materialises in memory or in a cache entry.
 *
 * Lookups are case- and accent-insensitive on (city_name, country_code).
 * Per-request results are memoised in-process so repeated lookups for the
 * same key (hit or miss) do not re-scan the CSV.
 */
class CityLookupService
{
    /** Per-request cache of resolved keys → [lat, lng] (or null). */
    protected array $hits = [];

    protected ?bool $hasDbTable = null;

    protected ?bool $csvMissingLogged = null;

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

        // 1) DB-backed reference table (the expected path).
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

        // 2) CSV fallback — streamed row-by-row so memory stays bounded
        //    even though the file is ~5 MB / 142k rows.
        $coords = $this->streamCsvLookup($cc, $norm);
        return $this->hits[$key] = $coords;
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

    /**
     * Streams the bundled CSV looking for a single (country_code, normalized)
     * match. O(n) per miss but allocates only one row at a time, so peak
     * memory is a few kB regardless of the file's size.
     */
    protected function streamCsvLookup(string $cc, string $norm): ?array
    {
        $path = $this->csvPath();
        if (!is_file($path)) {
            if ($this->csvMissingLogged !== true) {
                Log::warning("CityLookupService: CSV not found at {$path}");
                $this->csvMissingLogged = true;
            }
            return null;
        }

        $fh = fopen($path, 'r');
        if (!$fh) return null;

        try {
            fgetcsv($fh); // header
            while (($row = fgetcsv($fh)) !== false) {
                if (count($row) < 4) continue;
                $rowCc = strtoupper($row[0] ?? '');
                if ($rowCc !== $cc) continue;
                $name = $row[1] ?? '';
                if ($name === '') continue;
                if ($this->normalize($name) !== $norm) continue;

                $lat = (float) ($row[2] ?? 0);
                $lng = (float) ($row[3] ?? 0);
                if ($lat === 0.0 && $lng === 0.0) return null;

                return ['latitude' => $lat, 'longitude' => $lng];
            }
        } finally {
            fclose($fh);
        }
        return null;
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
