<?php

namespace Tests\Feature\Analytics;

use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Shared helpers for the analytics-related feature tests.
 */
abstract class AnalyticsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Analytics User',
            'timezone' => 'UTC',
            'language' => 'en',
        ], $overrides));
    }

    protected function makeLink(User $user, array $overrides = []): Link
    {
        return Link::create(array_merge([
            'user_id' => $user->id,
            'type' => 'url',
            'alias' => 'a' . bin2hex(random_bytes(4)),
            'long_url' => 'https://example.com/destination',
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Binds a fake GeoIpService that returns a fixed geo bundle for any IP.
     */
    protected function fakeGeoIp(array $geo): void
    {
        $mock = Mockery::mock(GeoIpService::class);
        $mock->shouldReceive('detectGeo')->andReturn(array_merge([
            'country_code' => null,
            'city' => null,
            'latitude' => null,
            'longitude' => null,
        ], $geo));
        $mock->shouldReceive('detectCountry')->andReturn($geo['country_code'] ?? null);
        $mock->shouldReceive('detectCity')->andReturn($geo['city'] ?? null);
        $mock->shouldReceive('detectCoordinates')->andReturn(
            isset($geo['latitude'], $geo['longitude'])
                ? ['latitude' => $geo['latitude'], 'longitude' => $geo['longitude']]
                : null
        );
        $mock->shouldReceive('lookup')->andReturn([]);
        $this->app->instance(GeoIpService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
