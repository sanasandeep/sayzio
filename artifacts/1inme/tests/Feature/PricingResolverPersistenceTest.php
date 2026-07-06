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
 * Cross-session persistence of the manual currency override.
 *
 * The visitor-facing rule (Task #441) is: a currency they explicitly
 * pick should still apply on a return visit, even after their session
 * has expired or been wiped.
 *
 * Anonymous visitors get this via a long-lived signed cookie; signed-in
 * users with no profile country also get the choice mirrored onto
 * `users.preferred_currency` so it follows them across devices. Users
 * with an explicit profile country are unaffected — their country
 * still wins.
 */
class PricingResolverPersistenceTest extends TestCase
{
    use RefreshDatabase;

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

    protected function bindRequest(string $ip, array $cookies = []): void
    {
        $request = Request::create('/pricing', 'GET', [], $cookies, [], ['REMOTE_ADDR' => $ip]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
    }

    protected function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    public function test_anonymous_cookie_override_survives_session_loss(): void
    {
        // Geo would say USD (US IP), but the visitor picked INR on a
        // previous visit and the choice rode along in their cookie.
        // No session entry — that's the whole point of the cookie.
        $this->fakeGeoCountry('US');
        $this->bindRequest('198.51.100.1', [PricingResolver::COOKIE_KEY => 'INR']);

        $this->assertSame('INR', PricingResolver::currencyForUser(null));
        $this->assertSame(
            PricingResolver::SOURCE_MANUAL,
            PricingResolver::currencySourceForUser(null),
        );
    }

    public function test_session_override_still_wins_over_cookie(): void
    {
        // Cookie says INR but the visitor just clicked USD this visit;
        // the in-session flag must take precedence.
        $this->fakeGeoCountry('IN');
        $this->bindRequest('203.0.113.1', [PricingResolver::COOKIE_KEY => 'INR']);
        session([PricingResolver::SESSION_KEY => 'USD']);

        $this->assertSame('USD', PricingResolver::currencyForUser(null));
    }

    public function test_invalid_cookie_value_is_ignored(): void
    {
        // A tampered/garbage cookie shouldn't influence the resolver —
        // it must fall through to the geo default.
        $this->fakeGeoCountry('US');
        $this->bindRequest('198.51.100.1', [PricingResolver::COOKIE_KEY => 'EUR']);

        $this->assertSame('USD', PricingResolver::currencyForUser(null));
        $this->assertSame(
            PricingResolver::SOURCE_GEO,
            PricingResolver::currencySourceForUser(null),
        );
    }

    public function test_user_preferred_currency_used_when_no_country(): void
    {
        // Signed-in user, no profile country, but they previously
        // switched to INR. Geo says USD; preferred_currency must win.
        $this->fakeGeoCountry('US');
        $this->bindRequest('198.51.100.1');

        $user = $this->makeUser([
            'country' => null,
            'preferred_currency' => 'INR',
        ]);

        $this->assertSame('INR', PricingResolver::currencyForUser($user));
        $this->assertSame(
            PricingResolver::SOURCE_MANUAL,
            PricingResolver::currencySourceForUser($user),
        );
    }

    public function test_profile_country_still_beats_preferred_currency(): void
    {
        // Country is the billing-of-record signal — even if the user
        // somehow has both set, country wins.
        $this->fakeGeoCountry('US');
        $this->bindRequest('198.51.100.1');

        $user = $this->makeUser([
            'country' => 'US',
            'preferred_currency' => 'INR',
        ]);

        $this->assertSame('USD', PricingResolver::currencyForUser($user));
        $this->assertSame(
            PricingResolver::SOURCE_USER_COUNTRY,
            PricingResolver::currencySourceForUser($user),
        );
    }

    public function test_remember_manual_choice_writes_user_column_when_no_country(): void
    {
        $this->bindRequest('198.51.100.1');

        $user = $this->makeUser(['country' => null]);
        PricingResolver::rememberManualChoice('INR', $user);

        $this->assertSame('INR', $user->fresh()->preferred_currency);
        $this->assertSame('INR', session(PricingResolver::SESSION_KEY));
    }

    public function test_remember_manual_choice_does_not_write_user_with_country(): void
    {
        // Users with a country shouldn't get the column written —
        // their country is authoritative and we don't want a stale
        // `preferred_currency` lurking around to confuse later code.
        $this->bindRequest('198.51.100.1');

        $user = $this->makeUser(['country' => 'IN', 'preferred_currency' => null]);
        PricingResolver::rememberManualChoice('USD', $user);

        $this->assertNull($user->fresh()->preferred_currency);
    }

    public function test_remember_manual_choice_returns_long_lived_cookie(): void
    {
        $this->bindRequest('198.51.100.1');
        $cookie = PricingResolver::rememberManualChoice('INR', null);

        $this->assertSame(PricingResolver::COOKIE_KEY, $cookie->getName());
        $this->assertSame('INR', $cookie->getValue());
        // Cookie expiry is "now + COOKIE_DAYS", give or take a few seconds.
        $expectedExpiry = time() + PricingResolver::COOKIE_DAYS * 24 * 60 * 60;
        $this->assertEqualsWithDelta($expectedExpiry, $cookie->getExpiresTime(), 60);
    }

    public function test_remember_manual_choice_clamps_garbage_to_usd(): void
    {
        $this->bindRequest('198.51.100.1');
        $user = $this->makeUser(['country' => null]);
        PricingResolver::rememberManualChoice('eur', $user);

        $this->assertSame('USD', $user->fresh()->preferred_currency);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
