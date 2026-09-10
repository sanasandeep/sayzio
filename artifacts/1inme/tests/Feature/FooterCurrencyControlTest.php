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
 * The footer currency control has to agree with the resolver.
 *
 * It did not. The partial used to ask for the active currency through
 * `PricingResolver::resolve()` -- a method that does not exist and never
 * has -- inside a try/catch whose fallback was `['USD', SOURCE_GEO]`. So
 * every single render threw "Call to undefined method", the catch ate it,
 * and the control was hard-wired to "USD, auto-detected" for every
 * visitor on every public page.
 *
 * That produced three separate visible faults from one cause:
 *
 *   1. The switcher looked dead. Clicking INR did persist -- the POST and
 *      the in-page re-render both worked -- but the next page load drew
 *      the control from the fallback again, so USD came back selected and
 *      the click appeared to have done nothing.
 *   2. Visitors in India, whom the resolver correctly puts on INR, were
 *      shown a footer insisting they were on USD.
 *   3. The country-locked branch (a signed-in user whose billing country
 *      fixes their currency) could never render, because the swallowed
 *      source was always SOURCE_GEO.
 *
 * The fix is to call the real API -- `currencyForUser()` plus
 * `currencySourceForUser()` -- and to call it OUTSIDE a catch-all, so a
 * future rename fails loudly instead of quietly serving everyone the
 * wrong currency.
 *
 * These tests pin the property that was violated: whatever the resolver
 * says, the control shows. They are written against rendered output
 * rather than the resolver alone, because the resolver was never the
 * broken part -- the wiring between it and the view was.
 */
class FooterCurrencyControlTest extends TestCase
{
    use RefreshDatabase;

    /** Map any non-private IP to the given country. */
    private function fakeGeoCountry(?string $cc): void
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

    private function bindRequestWithIp(string $ip): void
    {
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
    }

    private function renderFooter(): string
    {
        return (string) view('public.partials.footer')->render();
    }

    /**
     * The Alpine component is seeded with the currency the visitor is
     * actually on. This is the assertion the old code could not pass for
     * any INR visitor.
     */
    public function test_the_control_is_seeded_with_the_resolved_currency(): void
    {
        $this->fakeGeoCountry('IN');
        $this->bindRequestWithIp('203.0.113.1');

        $this->assertSame('INR', PricingResolver::currencyForUser(null), 'precondition: resolver puts this visitor on INR');
        $this->assertStringContainsString("currency: 'INR'", $this->renderFooter());
    }

    /** The other direction, so the test cannot pass by hard-coding INR. */
    public function test_a_visitor_outside_india_is_seeded_with_usd(): void
    {
        $this->fakeGeoCountry('DE');
        $this->bindRequestWithIp('203.0.113.2');

        $this->assertSame('USD', PricingResolver::currencyForUser(null));
        $this->assertStringContainsString("currency: 'USD'", $this->renderFooter());
    }

    /**
     * A manual choice survives the next render. This is the fault the
     * user reported as "footer currency switch not working": persistence
     * was fine, the control's own state was not.
     */
    public function test_a_manual_choice_is_reflected_on_the_next_render(): void
    {
        $this->fakeGeoCountry('DE');
        $this->bindRequestWithIp('203.0.113.3');

        session([PricingResolver::SESSION_KEY => 'INR']);

        $html = $this->renderFooter();
        $this->assertStringContainsString("currency: 'INR'", $html);
        // A deliberate pick is not an auto-detection, so the hint goes away.
        $this->assertStringNotContainsString('auto-detected', $html);
    }

    /** Geo-derived currency is labelled as such, and stays switchable. */
    public function test_a_geo_derived_currency_is_marked_auto_detected_and_switchable(): void
    {
        $this->fakeGeoCountry('IN');
        $this->bindRequestWithIp('203.0.113.4');

        $html = $this->renderFooter();
        $this->assertStringContainsString('auto-detected', $html);
        $this->assertStringContainsString('Choose display currency', $html, 'the switcher itself must still render');
    }

    /**
     * The branch that could never render before: a signed-in user whose
     * profile country fixes the currency gets a fixed label and a link to
     * change it, not a switcher that would silently disagree with billing.
     */
    public function test_a_billing_country_locks_the_currency_and_hides_the_switcher(): void
    {
        $this->fakeGeoCountry('DE');
        $this->bindRequestWithIp('203.0.113.5');

        $user = User::factory()->create(['country' => 'IN']);
        $this->actingAs($user);

        $this->assertSame(
            PricingResolver::SOURCE_USER_COUNTRY,
            PricingResolver::currencySourceForUser($user),
            'precondition: a profile country is what decides this currency'
        );

        $html = $this->renderFooter();
        $this->assertStringContainsString('₹ INR', $html);
        $this->assertStringContainsString('Set by your billing country', $html);
        $this->assertStringNotContainsString('Choose display currency', $html, 'a locked currency must not offer a switcher');
    }

    /**
     * The guard against the original bug class: the footer must not route
     * its currency through a catch-all that can turn a fatal into a
     * plausible-looking default. If the resolver call is ever renamed
     * again, the render should fail -- not quietly print USD.
     */
    public function test_the_partial_does_not_swallow_resolver_failures(): void
    {
        $source = file_get_contents(resource_path('views/public/partials/footer.blade.php'));

        $this->assertStringContainsString('currencyForUser', $source);
        $this->assertStringContainsString('currencySourceForUser', $source);
        $this->assertStringNotContainsString('PricingResolver::resolve(', $source);
        $this->assertDoesNotMatchRegularExpression(
            '/catch\s*\(\s*\\\\?Throwable[^)]*\)\s*\{[^}]*SOURCE_GEO/s',
            $source,
            'a swallowed failure must not fall back to a hard-coded currency'
        );
    }
}
