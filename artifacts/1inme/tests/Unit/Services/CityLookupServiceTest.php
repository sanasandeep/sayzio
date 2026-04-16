<?php

namespace Tests\Unit\Services;

use App\Modules\Common\Services\CityLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CityLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('cities')->insert([
            'country_code' => 'GB',
            'city_normalized' => 'london',
            'city_name' => 'London',
            'latitude' => 51.50853,
            'longitude' => -0.12574,
        ]);
    }

    private function service(): CityLookupService
    {
        return new CityLookupService();
    }

    public function test_it_resolves_a_known_city(): void
    {
        $service = $this->service();
        $coords = $service->lookup('London', 'GB');

        $this->assertIsArray($coords);
        $this->assertEqualsWithDelta(51.50853, $coords['latitude'], 0.0001);
        $this->assertEqualsWithDelta(-0.12574, $coords['longitude'], 0.0001);
    }

    public function test_lookup_is_case_and_whitespace_insensitive(): void
    {
        $service = $this->service();

        $this->assertNotNull($service->lookup('  LONDON  ', 'gb'));
        $this->assertNotNull($service->lookup('london', 'GB'));
    }

    public function test_unknown_city_returns_null(): void
    {
        $service = $this->service();
        // Uses a country code that does not exist in the bundled CSV so the
        // streaming fallback runs to completion without finding a match.
        $this->assertNull($service->lookup('Atlantis', 'ZZ'));
    }

    public function test_invalid_input_returns_null(): void
    {
        $service = $this->service();

        $this->assertNull($service->lookup(null, 'GB'));
        $this->assertNull($service->lookup('London', null));
        $this->assertNull($service->lookup('London', 'GBR')); // not ISO-2
        $this->assertNull($service->lookup('', 'GB'));
    }
}
