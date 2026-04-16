<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the `cities` reference table from two bundled public-domain
 * datasets in database/data/:
 *
 *   1. cities15000.txt — GeoNames cities >15k pop. (CC-BY 4.0). Provides
 *      `population` for the world's major cities (~26k rows).
 *   2. world-cities.csv — derived from lutangar/cities.json (~142k rows,
 *      no population), used to fill in the long tail of smaller places.
 *
 * Idempotent: skips when the table is already populated, and uses
 * insertOrIgnore against the unique (country_code, city_normalized)
 * index. Safe to re-run.
 */
class CitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('cities')) {
            $this->command?->warn('CitiesTableSeeder: `cities` table missing — run migrations first.');
            return;
        }
        if (DB::table('cities')->count() > 0) {
            $this->command?->info('CitiesTableSeeder: already populated, skipping.');
            return;
        }

        $inserted = 0;
        $seen = [];

        // 1) GeoNames first (gives us `population`)
        $geonames = database_path('data/cities15000.txt');
        if (is_file($geonames)) {
            $inserted += $this->ingestGeonames($geonames, $seen);
        } else {
            $this->command?->warn("CitiesTableSeeder: {$geonames} not found, skipping GeoNames step.");
        }

        // 2) CSV fallback for the long tail (no population)
        $csv = database_path('data/world-cities.csv');
        if (is_file($csv)) {
            $inserted += $this->ingestCsv($csv, $seen);
        } else {
            $this->command?->warn("CitiesTableSeeder: {$csv} not found, skipping CSV step.");
        }

        $this->command?->info("CitiesTableSeeder: inserted {$inserted} rows.");
    }

    protected function ingestGeonames(string $path, array &$seen): int
    {
        $fh = fopen($path, 'r');
        if (!$fh) return 0;

        $batch = [];
        $batchSize = 1000;
        $inserted = 0;

        while (($line = fgets($fh)) !== false) {
            $cols = explode("\t", rtrim($line, "\r\n"));
            if (count($cols) < 15) continue;

            $name       = $cols[1];      // utf-8 name
            $asciiName  = $cols[2] ?: $name;
            $lat        = (float) $cols[4];
            $lng        = (float) $cols[5];
            $cc         = strtoupper(trim($cols[8] ?? ''));
            $population = (int) ($cols[14] ?? 0);

            if (strlen($cc) !== 2) continue;
            if ($lat === 0.0 && $lng === 0.0) continue;

            // Index under both the localized and ASCII names
            foreach (array_unique([$name, $asciiName]) as $variant) {
                $norm = $this->normalize($variant);
                if ($norm === '') continue;
                $key = $cc . '|' . $norm;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                $batch[] = [
                    'country_code'    => $cc,
                    'city_normalized' => mb_substr($norm, 0, 120),
                    'city_name'       => mb_substr(trim($name), 0, 160),
                    'latitude'        => $lat,
                    'longitude'       => $lng,
                    'population'      => $population > 0 ? $population : null,
                ];

                if (count($batch) >= $batchSize) {
                    DB::table('cities')->insertOrIgnore($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }
        }
        if ($batch) {
            DB::table('cities')->insertOrIgnore($batch);
            $inserted += count($batch);
        }
        fclose($fh);
        return $inserted;
    }

    protected function ingestCsv(string $path, array &$seen): int
    {
        $fh = fopen($path, 'r');
        if (!$fh) return 0;
        fgetcsv($fh); // header

        $batch = [];
        $batchSize = 1000;
        $inserted = 0;

        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            $cc   = strtoupper(trim($row[0]));
            $name = trim($row[1]);
            $norm = $this->normalize($name);
            if (strlen($cc) !== 2 || $norm === '') continue;
            $lat = (float) $row[2];
            $lng = (float) $row[3];
            if ($lat === 0.0 && $lng === 0.0) continue;

            $key = $cc . '|' . $norm;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $batch[] = [
                'country_code'    => $cc,
                'city_normalized' => mb_substr($norm, 0, 120),
                'city_name'       => mb_substr($name, 0, 160),
                'latitude'        => $lat,
                'longitude'       => $lng,
                'population'      => null,
            ];

            if (count($batch) >= $batchSize) {
                DB::table('cities')->insertOrIgnore($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }
        if ($batch) {
            DB::table('cities')->insertOrIgnore($batch);
            $inserted += count($batch);
        }
        fclose($fh);
        return $inserted;
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
