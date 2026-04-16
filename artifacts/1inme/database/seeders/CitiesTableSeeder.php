<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the `cities` reference table from the bundled
 * database/data/world-cities.csv (~142k cities, public-domain data
 * derived from lutangar/cities.json).
 *
 * Idempotent: skips rows that are already present using a unique
 * (country_code, city_normalized) constraint.
 */
class CitiesTableSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/world-cities.csv');
        if (!is_file($path)) {
            $this->command?->warn("CitiesTableSeeder: CSV not found at {$path}");
            return;
        }

        if (DB::table('cities')->count() > 0) {
            $this->command?->info('CitiesTableSeeder: already populated, skipping.');
            return;
        }

        $fh = fopen($path, 'r');
        if (!$fh) return;
        fgetcsv($fh); // header

        $batch = [];
        $batchSize = 1000;
        $inserted = 0;
        $seen = [];

        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            $cc   = strtoupper(trim($row[0]));
            $norm = $this->normalize($row[1]);
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
                'city_name'       => mb_substr(trim($row[1]), 0, 160),
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

        $this->command?->info("CitiesTableSeeder: inserted {$inserted} rows.");
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
