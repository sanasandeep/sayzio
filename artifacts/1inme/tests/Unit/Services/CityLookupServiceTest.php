<?php

namespace Tests\Unit\Services;

use App\Modules\Common\Services\CityLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Analytics\TestCityLookupService;
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
        // Use a subclass that skips the huge bundled CSV fallback so these
        // unit tests remain fast and bounded in memory.
        return new TestCityLookupService();
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
        $this->assertNull($service->lookup('Atlantis', 'GB'));
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
