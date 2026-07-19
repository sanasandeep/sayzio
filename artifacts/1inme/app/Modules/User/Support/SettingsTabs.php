<?php

namespace App\Modules\User\Support;

use App\Modules\User\Services\WorkspacePermissions as WP;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for the consolidated user Settings hub
 * (Task #3220). Every scattered account/settings surface is registered
 * here as a priority-ordered top-level tab (each reachable at its own
 * /user/settings/{tab} URL) plus optional sub-tabs for the grouped
 * areas (Security, Connected Accounts & Apps, Verification & Badges).
 *
 * The hub layout (user/layouts/settings.blade.php) reads this to render
 * the primary tab strip + secondary sub-tab strip and to infer the
 * active tab/sub-tab purely from the current route name, so individual
 * settings views never have to know they live inside the hub.
 */
class SettingsTabs
{
    /**
     * Ordered tab definitions. Each tab:
     *  - key    : url/segment key
     *  - label  : sidebar/strip label
     *  - icon   : Font Awesome class
     *  - route  : route name the tab links to (its default surface)
     *  - match  : route-name patterns that light this tab up
     *  - not    : (optional) patterns that must NOT match (e.g. admin)
     *  - gate   : (optional) 'tasks_view' | 'api_access' extra gate
     *  - subs   : (optional) ordered sub-tab definitions (same shape,
     *             minus gate/subs)
     *
     * @return array<string,array<string,mixed>>
     */
    public static function tabs(): array
    {
        return [
            'profile' => [
                'label' => 'Profile',
                'icon'  => 'fa-user-circle',
                'route' => 'user.profile.edit',
                'match' => ['user.profile.*'],
            ],
            'creator' => [
                'label' => 'Creator Profile',
                'icon'  => 'fa-id-badge',
                'route' => 'user.creator-profile.edit',
                'match' => ['user.creator-profile.*'],
            ],
            'security' => [
                'label' => 'Security',
                'icon'  => 'fa-shield-halved',
                'route' => 'user.account.two-factor.show',
                'match' => [
                    'user.account.two-factor.show',
                    'user.settings.sessions.*',
                    'user.security.logins',
                    'user.merge.*',
                ],
                'subs' => [
                    'two-factor' => [
                        'label' => 'Two-factor',
                        'icon'  => 'fa-fingerprint',
                        'route' => 'user.account.two-factor.show',
                        'match' => ['user.account.two-factor.show'],
                    ],
                    'devices' => [
                        'label' => 'Devices & sessions',
                        'icon'  => 'fa-laptop',
                        'route' => 'user.settings.sessions.index',
                        'match' => ['user.settings.sessions.*'],
                    ],
                    'logins' => [
                        'label' => 'Recent logins',
                        'icon'  => 'fa-clock-rotate-left',
                        'route' => 'user.security.logins',
                        'match' => ['user.security.logins'],
                    ],
                    'merge' => [
                        'label' => 'Account merge',
                        'icon'  => 'fa-code-merge',
                        'route' => 'user.merge.start',
                        'match' => ['user.merge.*'],
                    ],
                ],
            ],
            'connections' => [
                'label' => 'Connected Accounts & Apps',
                'icon'  => 'fa-share-nodes',
                'route' => 'user.social-accounts.index',
                'match' => ['user.social-accounts.*', 'user.connected-apps.*'],
                'subs' => [
                    'accounts' => [
                        'label' => 'Connected Accounts',
                        'icon'  => 'fa-share-nodes',
                        'route' => 'user.social-accounts.index',
                        'match' => ['user.social-accounts.*'],
                    ],
                    'apps' => [
                        'label' => 'Connected Apps',
                        'icon'  => 'fa-plug-circle-bolt',
                        'route' => 'user.connected-apps.index',
                        'match' => ['user.connected-apps.*'],
                    ],
                ],
            ],
            'integrations' => [
                'label' => 'Integrations',
                'icon'  => 'fa-plug',
                'route' => 'user.integrations.index',
                'match' => ['user.integrations.*'],
            ],
            'domains' => [
                'label' => 'Custom Domains',
                'icon'  => 'fa-globe',
                'route' => 'user.domains.index',
                'match' => ['user.domains.*'],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'icon'  => 'fa-bell',
                'route' => 'user.notifications.preferences',
                'match' => ['user.notifications.preferences'],
            ],
            'privacy' => [
                'label' => 'Contact Privacy',
                'icon'  => 'fa-user-shield',
                'route' => 'user.settings.privacy.show',
                'match' => ['user.settings.privacy.*'],
            ],
            'billing' => [
                'label' => 'Billing & Identity',
                'icon'  => 'fa-building',
                'route' => 'user.billing.companies.index',
                'match' => ['user.billing.companies.*'],
                'gate'  => 'tasks_view',
            ],
            'developer' => [
                'label' => 'Developer / API',
                'icon'  => 'fa-key',
                'route' => 'user.api-keys.index',
                'match' => ['user.api-keys.*', 'user.settings.webhooks.*'],
                'gate'  => 'api_access',
                'subs'  => [
                    'api-keys' => [
                        'label' => 'API Keys',
                        'icon'  => 'fa-key',
                        'route' => 'user.api-keys.index',
                        'match' => ['user.api-keys.*'],
                    ],
                    'webhooks' => [
                        'label' => 'Webhooks',
                        'icon'  => 'fa-bolt',
                        'route' => 'user.settings.webhooks.index',
                        'match' => ['user.settings.webhooks.*'],
                    ],
                ],
            ],
            'verification' => [
                'label' => 'Verification & Badges',
                'icon'  => 'fa-check-circle',
                'route' => 'user.verification.index',
                'match' => ['user.verification.*', 'user.badge-requests.*'],
                'not'   => ['user.verification.admin*'],
                'subs' => [
                    'verification' => [
                        'label' => 'Verification',
                        'icon'  => 'fa-check-circle',
                        'route' => 'user.verification.index',
                        'match' => ['user.verification.*'],
                        'not'   => ['user.verification.admin*'],
                    ],
                    'badges' => [
                        'label' => 'Badges',
                        'icon'  => 'fa-award',
                        'route' => 'user.badge-requests.index',
                        'match' => ['user.badge-requests.*'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Tabs the current user can actually reach, in priority order.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function visibleTabs(): array
    {
        // The entire hub already sits behind the settings.view gate, so a
        // user who reaches it can see every tab except the two that carry
        // an extra gate (billing needs the invoicing permission, developer
        // needs the api_access plan feature).
        return array_filter(self::tabs(), fn (array $tab) => self::gatePasses($tab['gate'] ?? null));
    }

    /**
     * True when a tab's extra gate (if any) is satisfied for the user.
     */
    public static function gatePasses(?string $gate): bool
    {
        if ($gate === null) {
            return true;
        }

        if ($gate === 'tasks_view') {
            return WP::userCan('tasks.view');
        }

        if ($gate === 'api_access') {
            $user = Auth::user();

            return $user !== null && $user->planFeatureEnabled('api_access');
        }

        return true;
    }

    /**
     * Does the current route match a tab/sub-tab's patterns?
     *
     * @param array<int,string> $match
     * @param array<int,string> $not
     */
    public static function matches(array $match, array $not = []): bool
    {
        $request = request();

        foreach ($not as $pattern) {
            if ($request->routeIs($pattern)) {
                return false;
            }
        }

        foreach ($match as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Key of the tab that owns the current route, if any.
     */
    public static function activeKey(): ?string
    {
        foreach (self::tabs() as $key => $tab) {
            if (self::matches($tab['match'], $tab['not'] ?? [])) {
                return $key;
            }
        }

        return null;
    }
}
