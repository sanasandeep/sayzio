<?php

namespace Tests\Unit\Support;

use App\Modules\User\Support\SettingsTabs;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Unit coverage for the Settings hub active-tab resolver (Task #3220).
 *
 * SettingsTabs::activeKey() / matches() infer which hub tab (and sub-tab)
 * owns the current request purely from its route NAME. The hub layout
 * relies on this to light up the right tab strip entry, so a mis-mapped
 * `match` pattern would silently render the wrong active tab with no other
 * failing test. These cases pin the mapping for a representative route from
 * every tab, plus the `not` exclusions that keep admin surfaces out of the
 * user-facing Verification tab.
 *
 * matches()/activeKey() read request()->routeIs(), so each case binds a
 * fake Request carrying a named Route into the container.
 */
class SettingsTabsActiveKeyTest extends TestCase
{
    /** Bind a fake current request whose route carries the given name. */
    private function onRoute(string $name): void
    {
        $route = new Route(['GET'], '/fake', ['uses' => fn () => null]);
        $route->name($name);

        $request = Request::create('/fake', 'GET');
        $request->setRouteResolver(fn () => $route);

        $this->app->instance('request', $request);
    }

    /**
     * @return array<string,array{0:string,1:?string}>
     */
    public static function tabForRoute(): array
    {
        return [
            'profile edit'          => ['user.profile.edit',                'profile'],
            'creator profile'       => ['user.creator-profile.edit',        'creator'],
            'two-factor'            => ['user.account.two-factor.show',      'security'],
            'devices & sessions'    => ['user.settings.sessions.index',      'security'],
            'recent logins'         => ['user.security.logins',             'security'],
            'account merge'         => ['user.merge.start',                 'security'],
            'connected accounts'    => ['user.social-accounts.index',       'connections'],
            'connected apps'        => ['user.connected-apps.index',        'connections'],
            'integrations'          => ['user.integrations.index',          'integrations'],
            'custom domains'        => ['user.domains.index',               'domains'],
            'notifications'         => ['user.notifications.preferences',    'notifications'],
            'billing & identity'    => ['user.billing.companies.index',     'billing'],
            'developer / api'       => ['user.api-keys.index',              'developer'],
            'verification'          => ['user.verification.index',          'verification'],
            'badges'                => ['user.badge-requests.index',        'verification'],
            // The admin review surface must NOT light up the user Verification
            // tab — the tab's `not` pattern excludes it and nothing else owns
            // it, so it resolves to no tab.
            'admin verification'    => ['user.verification.admin',          null],
            // A route outside the hub belongs to no tab.
            'dashboard'             => ['user.dashboard',                   null],
        ];
    }

    /**
     * @dataProvider tabForRoute
     */
    public function test_active_key_maps_route_to_expected_tab(string $routeName, ?string $expected): void
    {
        $this->onRoute($routeName);

        $this->assertSame($expected, SettingsTabs::activeKey());
    }

    public function test_matches_resolves_the_expected_sub_tab_within_security(): void
    {
        $subs = SettingsTabs::tabs()['security']['subs'];

        // On the devices route, the "devices" sub matches and "two-factor" does not.
        $this->onRoute('user.settings.sessions.index');
        $this->assertTrue(SettingsTabs::matches($subs['devices']['match'], $subs['devices']['not'] ?? []));
        $this->assertFalse(SettingsTabs::matches($subs['two-factor']['match'], $subs['two-factor']['not'] ?? []));

        // On the two-factor route, the roles flip.
        $this->onRoute('user.account.two-factor.show');
        $this->assertTrue(SettingsTabs::matches($subs['two-factor']['match'], $subs['two-factor']['not'] ?? []));
        $this->assertFalse(SettingsTabs::matches($subs['devices']['match'], $subs['devices']['not'] ?? []));
    }

    public function test_not_pattern_excludes_admin_from_verification_sub(): void
    {
        $verificationSub = SettingsTabs::tabs()['verification']['subs']['verification'];

        // The user verification index matches the sub…
        $this->onRoute('user.verification.index');
        $this->assertTrue(SettingsTabs::matches($verificationSub['match'], $verificationSub['not'] ?? []));

        // …but the admin review route is excluded by the `not` pattern.
        $this->onRoute('user.verification.admin');
        $this->assertFalse(SettingsTabs::matches($verificationSub['match'], $verificationSub['not'] ?? []));
    }
}
