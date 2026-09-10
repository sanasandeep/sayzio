<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the legacy-URL → Settings hub redirects added in Task #3220.
 *
 * That task consolidated ~10 scattered account/settings surfaces under a
 * single hub at /user/settings/{tab} and left ~15 `Route::redirect`
 * entries so old bookmarks/links (e.g. /user/profile, /user/verification,
 * /user/settings/sessions) still land users in the correct hub tab.
 *
 * Two things can silently break with no failing test:
 *   1. A future route rename could point a legacy redirect at the wrong
 *      (or a now-missing) hub tab — bookmarks 404 or land on the wrong page.
 *   2. A redirect swallowing a real mutation that shares its path (e.g.
 *      POST /user/social-accounts, PUT /user/notifications/preferences),
 *      answering 302 to the hub tab and saving nothing.
 *
 *      This file used to say the redirects "must stay registered LAST",
 *      on the theory that Route::redirect answers any verb and a later
 *      registration loses. It is the other way round.
 *      RouteCollection::addToCollections() stores routes as
 *      routes[$method][$uri], so a LATER route with the same method and
 *      URI replaces the earlier one -- registering the any-verb redirect
 *      last is exactly what made it win. Six real handlers were being
 *      swallowed that way. The redirects are GET-only now, and
 *      scripts/check-route-shadowing.php fails CI on any method+URI pair
 *      claimed twice, anywhere in the app.
 *
 * Both are covered here against a signed-in workspace owner.
 */
class SettingsHubLegacyRedirectsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $plan = Plan::create([
            'name' => 'p' . Str::random(6), 'slug' => 'p' . Str::random(6),
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            // api_access unlocks the Developer tab landing so the /user/api-keys
            // redirect isn't gated away before it can fire.
            'features' => ['api_access' => true],
        ]);

        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            // Skip the onboarding gate so requests aren't bounced to setup.
            'onboarded_at' => now(),
        ]);
    }

    /**
     * Every legacy GET landing → its canonical hub tab. Keeping this map in
     * lockstep with the `Route::redirect` block in routes/modules/user.php is
     * the whole point: a rename that repoints a tab without fixing the
     * redirect fails here.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function legacyRedirects(): array
    {
        return [
            'hub root'              => ['/user/settings',                 '/user/settings/profile'],
            'profile'               => ['/user/profile',                  '/user/settings/profile'],
            'creator profile'       => ['/user/creator-profile',          '/user/settings/creator'],
            'two-factor'            => ['/user/account/two-factor',       '/user/settings/security'],
            'recent logins'         => ['/user/security/logins',          '/user/settings/security/logins'],
            'devices & sessions'    => ['/user/settings/sessions',        '/user/settings/security/devices'],
            'account merge'         => ['/user/merge',                    '/user/settings/security/merge'],
            'connected accounts'    => ['/user/social-accounts',          '/user/settings/connections'],
            'connected apps'        => ['/user/connected-apps',           '/user/settings/connections/apps'],
            'integrations'          => ['/user/integrations',             '/user/settings/integrations'],
            'custom domains'        => ['/user/domains',                  '/user/settings/domains'],
            'notifications'         => ['/user/notifications/preferences', '/user/settings/notifications'],
            'billing & identity'    => ['/user/billing/companies',        '/user/settings/billing'],
            'developer / api'       => ['/user/api-keys',                 '/user/settings/developer'],
            'verification'          => ['/user/verification',             '/user/settings/verification'],
            'badges'                => ['/user/badge-requests',           '/user/settings/verification/badges'],
        ];
    }

    #[DataProvider('legacyRedirects')]
    public function test_legacy_get_path_redirects_into_correct_hub_tab(string $from, string $to): void
    {
        $resp = $this->actingAs($this->owner())->get($from);

        $resp->assertStatus(302);
        $resp->assertRedirect($to);
    }

    /**
     * Every real mutation route whose path is ALSO a legacy redirect target.
     * Each tuple is [method, path, hub tab the redirect would send it to].
     * The response must NOT be a redirect to that tab -- that is the
     * fingerprint of the legacy redirect having swallowed the mutation.
     *
     * All six that were actually broken are listed. The four this file
     * started with were the right idea; the two it was missing (DELETE
     * two-factor, POST billing companies) were just as dead.
     *
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function shadowableMutations(): array
    {
        return [
            // [method, path, hub target the redirect would send it to]
            'POST social-accounts'        => ['post',   '/user/social-accounts',            '/user/settings/connections'],
            'PUT notifications/prefs'     => ['put',    '/user/notifications/preferences',  '/user/settings/notifications'],
            'POST api-keys'               => ['post',   '/user/api-keys',                   '/user/settings/developer'],
            'POST account/two-factor'     => ['post',   '/user/account/two-factor',         '/user/settings/security'],
            'DELETE account/two-factor'   => ['delete', '/user/account/two-factor',         '/user/settings/security'],
            'POST billing/companies'      => ['post',   '/user/billing/companies',          '/user/settings/billing'],
        ];
    }

    #[DataProvider('shadowableMutations')]
    public function test_real_mutation_is_not_shadowed_by_any_verb_redirect(string $method, string $path, string $hubTarget): void
    {
        // Empty body → the controller runs and (typically) fails validation
        // and redirects back / returns 422. Either way it reached the real
        // route. What must NEVER happen is a 302 straight to the hub tab,
        // which is the fingerprint of the any-verb redirect shadowing it.
        $resp = $this->actingAs($this->owner())->{$method}($path, []);

        $location = $resp->headers->get('Location');

        // Both sides go through url() before comparing, and that is the
        // whole point. This assertion used to read
        // assertNotSame(url($hubTarget), $location) -- comparing an
        // ABSOLUTE url ("http://localhost/user/settings/notifications")
        // against the RELATIVE Location header Laravel actually emits
        // ("/user/settings/notifications"). Those two strings are never
        // identical, so the assertion could not fail. This test was written
        // for exactly the bug that then shipped, and passed green through
        // all of it. Normalizing both sides is what gives it teeth.
        $this->assertNotSame(
            url($hubTarget),
            $location === null ? null : url($location),
            "{$method} {$path} was shadowed by the legacy hub redirect ({$hubTarget})."
        );
    }
}
