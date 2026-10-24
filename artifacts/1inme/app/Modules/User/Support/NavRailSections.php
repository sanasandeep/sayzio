<?php

namespace App\Modules\User\Support;

/**
 * The seven sections of the rail navigation.
 *
 * WHY THIS IS A CLASS AND NOT MARKUP
 *
 * The rail has to know which of its seven icons is lit before Alpine
 * runs, so the panel opens on the section you are actually in rather
 * than snapping to it after paint. That means route matching in PHP.
 * Putting it here instead of in the Blade also makes the one thing
 * worth testing testable: that no destination went missing when ten
 * groups became seven sections.
 *
 * THE ROUTE PATTERNS ARE A SECOND COPY AND THAT IS A REAL COST
 *
 * Each classic group already computes its own `$grpFooActive` to decide
 * whether to start expanded. Those expressions stay where they are --
 * they control a group's collapse state, which is a different question
 * from which rail icon is lit. So the patterns below are a second copy,
 * and a route renamed in a group partial without being renamed here
 * lights the wrong icon. OneNavForBothScreensTest pins every route
 * named in a section partial to a pattern in this map, which is what
 * stops that drift from being silent.
 *
 * ORDER MATTERS. `events` is checked first because "My Events" is
 * `user.links.index` with `?type=ics` on it -- the same route name
 * "All Links" uses under `home`. Nothing else overlaps.
 */
class NavRailSections
{
    /**
     * key => [label, icon, partial, routes]
     *
     * `label` is what sits under the rail icon and in its tooltip.
     * `routes` are route-name patterns for request()->routeIs().
     *
     * @var array<string, array<string, mixed>>
     */
    public const SECTIONS = [
        'events' => [
            'label'   => 'Events',
            'icon'    => 'fa-calendar-check',
            'partial' => 'user.partials.nav._events',
            'routes'  => [
                'user.links.ics.*',
                'user.events.*',
                'user.calendars.*',
                'user.calendar.*',
            ],
        ],
        'home' => [
            'label'   => 'Home',
            'icon'    => 'fa-house',
            'partial' => 'user.partials.nav._home',
            'routes'  => [
                'user.dashboard',
                'user.attendance.*',
                'user.links.index',
                'user.links.show',
                'user.links.create',
                'user.projects.*',
                'user.inbox.*',
                'user.notifications.*',
                'user.stats.*',
                'user.visitors.*',
            ],
        ],
        'links' => [
            'label'   => 'Links',
            'icon'    => 'fa-link',
            'partial' => 'user.partials.nav._links',
            'routes'  => [
                'user.qr-codes.*',
                'user.qrcode*',
                'user.forms.*',
                'user.backlinks.*',
                'user.splash-pages.*',
                'user.resume.*',
                'user.files.*',
                'user.cloud-files.*',
                'user.cloud-oauth.*',
                'user.payouts.*',
                'user.adult-content.*',
            ],
        ],
        'audience' => [
            'label'   => 'Audience',
            'icon'    => 'fa-user-group',
            'partial' => 'user.partials.nav._audience',
            'routes'  => [
                'user.subscribers.*',
                'user.followers.*',
                'user.following.*',
                'user.feed.*',
                'feed.*',
                'user.posts.*',
                'user.leads.*',
                'user.contacts.*',
                'user.dialer.*',
                'user.creator-profile.*',
                'user.social-proofs.*',
                'user.pixels.*',
                'user.referrals.*',
            ],
        ],
        'ai' => [
            'label'   => 'AI',
            'icon'    => 'fa-brain',
            'partial' => 'user.partials.nav._ai',
            'routes'  => [
                'user.ai.*',
                'user.minds.*',
                'user.ai-personas.*',
                'user.ai-companions.*',
                'user.brand-kits.*',
                // AI Marketing Studio resolves its href at runtime:
                // Route::has('user.marketing-studio.index') ? that :
                // 'user.brand-studio.index'. Both names have to be here or
                // the rail lights the wrong icon for whichever half of that
                // branch an environment actually has. Caught by
                // test_every_route_in_a_section_partial, which is the whole
                // reason that test reads the partials instead of trusting
                // this list.
                'user.marketing-studio.*',
                'user.brand-studio.*',
                'user.marketing-plan.*',
            ],
        ],
        'money' => [
            'label'   => 'Money',
            'icon'    => 'fa-sack-dollar',
            'partial' => 'user.partials.nav._money',
            'routes'  => [
                'user.monetization.*',
                'user.billing.*',
                'user.client-invoices.*',
            ],
        ],
        'settings' => [
            'label'   => 'Settings',
            'icon'    => 'fa-sliders',
            'partial' => 'user.partials.nav._settings',
            'routes'  => [
                'user.tasks.*',
                'user.delivery-projects.*',
                'user.vault.*',
                'user.profile.*',
                'user.identifiers.*',
                'user.email-connections.*',
                'user.emails.*',
            ],
        ],
    ];

    /**
     * Rail order, top to bottom.
     *
     * Separate from the SECTIONS key order because SECTIONS is ordered
     * for MATCHING (events first, for the ?type=ics clash) and the rail
     * is ordered for READING (home first, settings last). Two different
     * jobs; conflating them would mean the rail opens with Events at
     * the top because of a route-matching detail, which is not a reason
     * any user would recognise.
     *
     * @return array<int, string>
     */
    public static function order(): array
    {
        return ['home', 'links', 'events', 'audience', 'ai', 'money', 'settings'];
    }

    /**
     * Which section the current request sits in.
     *
     * Falls back to 'home', which is also where a route nobody mapped
     * ends up. That is deliberate: an unmapped page showing the Home
     * panel is a mild wrong answer, and showing no panel at all is a
     * sidebar that looks broken.
     */
    public static function activeKey(): string
    {
        // "My Events" is user.links.index with ?type=ics, which is the
        // same route name All Links uses. The query string is the only
        // thing that separates them, so it is settled before the
        // general matching runs.
        if (request()->routeIs('user.links.index') && request('type') === 'ics') {
            return 'events';
        }

        foreach (self::SECTIONS as $key => $section) {
            if (nav_route_is(...$section['routes'])) {
                return $key;
            }
        }

        return 'home';
    }
}
