<?php

namespace Tests\Feature\Analytics;

use App\Modules\Common\Services\CityLookupService;

/**
 * Test double that skips the ~5MB world-cities.csv fallback so we
 * can exercise the DB-backed lookup path without loading the CSV
 * into memory / the file cache.
 */
class TestCityLookupService extends CityLookupService
{
    protected function csvPath(): string
    {
        return __DIR__ . '/__does_not_exist__.csv';
    }
}
