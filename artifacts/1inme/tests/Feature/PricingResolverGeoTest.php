<?php

namespace Tests\Feature;

use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

/**
 * Geo-IP defaulting for the anonymous currency resolver.
 *
 * Visitors browsing from an Indian IP should default to INR; visitors
 * from anywhere else should default to USD. The existing precedence
 * (user country > session override > geo default > USD fallback) must
 * still hold, and private/loopback IPs must not throw.
 */
class PricingResolverGeoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bind a fake GeoIpService that maps any non-private IP to the
     * given country code. Private IPs always return null (the real
     * service short-circuits on private ranges).
     */
    protected function fakeGeoCountry(?string $cc): void
    {
        $mock = Mockery::mock(GeoIpService::class);
        $mock->shouldReceive('detectCountry')->andReturn($cc);
        $mock->shouldReceive('lookup')->andReturn([]);
        $mock->shouldReceive('detectCity')->andReturn(null);
        $mock->shouldReceive('detectCoordinates')->andReturn(null);
        $mock->shouldReceive('detectGeo')->andReturn([
            'country_code' => $cc, 'city' => null,
            'latitude' => null, 'longitude' => null,
        ]);
        $this->app->instance(GeoIpService::class, $mock);
    }

    protected function bindRequestWithIp(string $ip): void
    {
        $request = Request::create('/pricing', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
    }

    public function test_indian_ip_anonymous_defaults_to_inr(): void
    {
        $this->fakeGeoCountry('IN');
        $this->bindRequestWithIp('203.0.113.1');

        $this->assertSame('INR', PricingResolver::currencyForUser(null));
    }

    public function test_non_indian_ip_anonymous_defaults_to_usd(): void
    {
        $this->fakeGeoCountry('US');
        $this->bindRequestWithIp('198.51.100.1');

        $this->assertSame('USD', PricingResolver::currencyForUser(null));
    }

    public function test_logged_in_user_country_wins_over_geo(): void
    {
        // Geo would say INR, but the user's profile country is US.
        $this->fakeGeoCountry('IN');
        $this->bindRequestWithIp('203.0.113.1');

        $user = new User(['country' => 'US']);
        $this->assertSame('USD', PricingResolver::currencyForUser($user));

        // And the reverse: geo says US, user country is IN.
        $this->fakeGeoCountry('US');
        session()->forget(PricingResolver::SESSION_KEY_GEO);
        $user2 = new User(['country' => 'IN']);
        $this->assertSame('INR', PricingResolver::currencyForUser($user2));
    }

    public function test_session_override_wins_over_geo(): void
    {
        $this->fakeGeoCountry('IN');
        $this->bindRequestWithIp('203.0.113.1');

        // Manual switcher set USD; that must beat the IN geo default.
        session([PricingResolver::SESSION_KEY => 'USD']);
        $this->assertSame('USD', PricingResolver::currencyForUser(null));

        session([PricingResolver::SESSION_KEY => 'INR']);
        $this->fakeGeoCountry('US');
        $this->assertSame('INR', PricingResolver::currencyForUser(null));
    }

    public function test_private_ip_falls_back_to_usd(): void
    {
        // Real GeoIpService returns null for private IPs; the resolver
        // must not throw and must land on USD.
        $this->fakeGeoCountry(null);
        $this->bindRequestWithIp('127.0.0.1');

        $this->assertSame('USD', PricingResolver::currencyForUser(null));
    }

    public function test_geo_lookup_is_cached_on_session(): void
    {
        $mock = Mockery::mock(GeoIpService::class);
        // Strict: detectCountry must only be called ONCE across two
        // PricingResolver invocations on the same session.
        $mock->shouldReceive('detectCountry')->once()->andReturn('IN');
        $this->app->instance(GeoIpService::class, $mock);

        $this->bindRequestWithIp('203.0.113.7');

        $this->assertSame('INR', PricingResolver::currencyForUser(null));
        $this->assertSame('INR', PricingResolver::currencyForUser(null));
        $this->assertSame(
            'INR',
            session(PricingResolver::SESSION_KEY_GEO),
            'Geo currency must be cached on the session.',
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
