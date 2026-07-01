<?php

namespace Tests\Feature;

use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
use App\Modules\User\Models\User;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the EnsureFeatureAvailable route guard — the middleware that stops a
 * user from bypassing the "Soon" badge by typing a gated feature's URL
 * directly. When the feature resolves to "coming soon" the request must be
 * redirected to the branded preview page (user.coming-soon.show) instead of
 * reaching a half-wired controller; when the feature is ready the same route
 * must pass straight through.
 *
 * The `connected_apps` feature is used as the exemplar because it is
 * config-driven: it auto-resolves to "coming soon" until an admin wires up a
 * provider (here Google Analytics), which lets us exercise both the auto and
 * the admin-forced override paths without touching the controller internals.
 */
class EnsureFeatureAvailableGuardTest extends TestCase
{
    use RefreshDatabase;

    private const GUARDED_ROUTE = 'user.connected-apps.index';
    private const FEATURE_KEY   = 'connected_apps';

    private function user(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            // Skip the onboarding redirect so we reach the guard, not onboarding.
            'onboarded_at' => now(),
        ]);
    }

    /** Make the connected_apps feature auto-resolve to "ready". */
    private function makeFeatureReady(): void
    {
        // Any wired provider flips connectedAppsConfigured() to true; enabling
        // GA is the cheapest signal (no OAuth client credentials needed).
        PlatformServiceSettings::setGoogleAnalyticsEnabled(true);
        FeatureAvailability::setForced(self::FEATURE_KEY, false);
    }

    public function test_guarded_route_redirects_to_coming_soon_when_feature_is_coming_soon(): void
    {
        // No provider configured + no admin force ⇒ auto "coming soon".
        PlatformServiceSettings::setGoogleAnalyticsEnabled(false);
        FeatureAvailability::setForced(self::FEATURE_KEY, false);

        $this->assertTrue(
            FeatureAvailability::isComingSoon(self::FEATURE_KEY),
            'Precondition: connected_apps should be coming soon with nothing configured.'
        );

        $resp = $this->actingAs($this->user())->get(route(self::GUARDED_ROUTE, [], false));

        $resp->assertRedirect(
            route('user.coming-soon.show', ['feature' => self::FEATURE_KEY], false)
        );
    }

    public function test_guarded_route_passes_through_when_feature_is_ready(): void
    {
        $this->makeFeatureReady();

        $this->assertFalse(
            FeatureAvailability::isComingSoon(self::FEATURE_KEY),
            'Precondition: connected_apps should be ready once a provider is configured.'
        );

        $resp = $this->actingAs($this->user())->get(route(self::GUARDED_ROUTE, [], false));

        // The guard must NOT hijack a ready feature onto the preview page.
        $this->assertNotSame(
            route('user.coming-soon.show', ['feature' => self::FEATURE_KEY], false),
            $resp->headers->get('Location'),
            'A ready feature must not be redirected to the coming-soon preview page.'
        );
    }

    public function test_admin_forced_override_flips_a_ready_feature_to_coming_soon(): void
    {
        // Start from a genuinely ready feature…
        $this->makeFeatureReady();
        $this->assertFalse(FeatureAvailability::isComingSoon(self::FEATURE_KEY));

        // …then the admin manually forces it to "coming soon".
        FeatureAvailability::setForced(self::FEATURE_KEY, true);

        $this->assertTrue(
            FeatureAvailability::isComingSoon(self::FEATURE_KEY),
            'The admin force override should mark the feature coming soon even when configured.'
        );

        $resp = $this->actingAs($this->user())->get(route(self::GUARDED_ROUTE, [], false));

        $resp->assertRedirect(
            route('user.coming-soon.show', ['feature' => self::FEATURE_KEY], false)
        );
    }
}
