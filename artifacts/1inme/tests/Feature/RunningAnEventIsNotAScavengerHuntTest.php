<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\EventsModule;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Running an event touches six pages, and until now none of them knew
 * about the others. They were wired as a chain rather than a hub: People
 * went "back" to Ticketing, Messages went "back" to RSVPs, and there was
 * no path at all from the door scanner to the guest list. On the day
 * that means going out to All Links and back in between every task.
 *
 * What these tests actually pin down:
 *
 * - The bar is on every one of the seven pages, including the event's own
 *   Overview, which is the landing page and had no way into any of the
 *   management surfaces at all.
 * - Exactly one tab is marked current, and it is the page you are on. A
 *   bar that highlights the wrong tab is worse than no bar.
 * - Every href carries THIS event's id. Seven pages each building their
 *   own links is how one of them quietly ends up pointing at another
 *   event, and nothing would fail loudly.
 * - A tab the viewer cannot reach is absent rather than present-and-403.
 *   Workspace permissions collapse the `feature.` prefix, so `links.view`
 *   and `followers.view` both resolve to the single `view` action and a
 *   viewer sees everything except Edit, which needs `edit`.
 * - No bar on a link that is not an event, and none when the Events
 *   module is switched off -- those routes 404 then, and a bar of 404s is
 *   worse than the scavenger hunt it replaced.
 */
class RunningAnEventIsNotAScavengerHuntTest extends TestCase
{
    use RefreshDatabase;

    private const TABS = ['Overview', 'Edit', 'Tickets', 'RSVPs', 'People', 'Check-in', 'Messages'];

    private function owner(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    private function event(User $host, string $type = 'ics'): Link
    {
        $link = new Link([
            'user_id'   => $host->id,
            'type'      => $type,
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Founder Sessions',
            'is_active' => true,
        ]);
        $link->workspace_id = $host->ownedWorkspaces()->first()->id;
        $link->save();

        if ($type === 'ics') {
            IcsData::create([
                'link_id'    => $link->id,
                'event_name' => 'Founder Sessions',
                'start_date' => now()->addDay()->toDateTimeString(),
                'end_date'   => now()->addDay()->addHours(2)->toDateTimeString(),
                'timezone'   => 'UTC',
            ]);
        }

        return $link->fresh();
    }

    /**
     * The tab bar as a list of [label, href, isCurrent], or [] when the
     * page renders no bar at all.
     *
     * @return array<int,array{label:string,href:string,current:bool}>
     */
    private function tabs(string $html): array
    {
        if (!preg_match('~<nav class="evtabs"[^>]*>(.*?)</nav>~s', $html, $nav)) {
            return [];
        }

        preg_match_all('~<a\s+href="([^"]+)"(.*?)</a>~s', $nav[1], $anchors, PREG_SET_ORDER);

        return array_map(static function (array $a): array {
            // The label comes from the <span>, not from strip_tags on the
            // whole match: $a[2] begins after the href value and still
            // carries the rest of the opening tag, so stripping tags
            // leaves the class and aria attributes sitting in the label.
            preg_match('~<span>(.*?)</span>~s', $a[2], $span);

            return [
                'label'   => trim($span[1] ?? ''),
                'href'    => $a[1],
                'current' => str_contains($a[2], 'aria-current="page"'),
            ];
        }, $anchors);
    }

    /** @return array<int,array{label:string,href:string,current:bool}> */
    private function tabsOn(User $user, string $url): array
    {
        return $this->tabs((string) $this->actingAs($user)->get($url)->assertOk()->getContent());
    }

    /** Every management page for one event, keyed by the tab that owns it. */
    private function pages(Link $link): array
    {
        return [
            'Overview' => route('user.links.show', $link, false),
            'Edit'     => route('user.links.ics.edit', $link, false),
            'Tickets'  => route('user.links.ics.tickets', $link, false),
            'RSVPs'    => route('user.links.rsvps.index', $link, false),
            'People'   => route('user.links.ics.people', $link, false),
            'Check-in' => route('user.links.ics.checkin', $link, false),
            'Messages' => route('user.links.ics.broadcast', $link, false),
        ];
    }

    public function test_every_page_carries_the_whole_bar(): void
    {
        $user = $this->owner();
        $link = $this->event($user);

        foreach ($this->pages($link) as $where => $url) {
            $labels = array_column($this->tabsOn($user, $url), 'label');

            $this->assertSame(
                self::TABS,
                $labels,
                "The {$where} page should carry every tab, in order"
            );
        }
    }

    public function test_the_page_you_are_on_is_the_tab_that_is_lit(): void
    {
        $user = $this->owner();
        $link = $this->event($user);

        foreach ($this->pages($link) as $where => $url) {
            $current = array_values(array_filter(
                $this->tabsOn($user, $url),
                static fn (array $t): bool => $t['current']
            ));

            $this->assertCount(1, $current, "The {$where} page should mark exactly one tab current");
            $this->assertSame($where, $current[0]['label'], "The {$where} page lit the wrong tab");
        }
    }

    public function test_every_tab_points_at_this_event_and_not_another(): void
    {
        $user  = $this->owner();
        $mine  = $this->event($user);
        $other = $this->event($user);

        $expected = array_values($this->pages($mine));

        foreach ($this->pages($mine) as $where => $url) {
            $hrefs = array_map(
                // Hrefs render absolute; compare the path so the test does
                // not depend on the request host.
                static fn (array $t): string => (string) parse_url($t['href'], PHP_URL_PATH),
                $this->tabsOn($user, $url)
            );

            $this->assertSame($expected, $hrefs, "A tab on the {$where} page points somewhere else");
            $this->assertNotContains(
                route('user.links.ics.tickets', $other, false),
                $hrefs,
                "The {$where} page leaked a link to a different event"
            );
        }
    }

    public function test_a_viewer_gets_no_edit_tab_rather_than_a_403(): void
    {
        $ownerUser = $this->owner();
        $link      = $this->event($ownerUser);

        $ws = Workspace::create([
            'owner_user_id' => $ownerUser->id,
            'name'          => 'WS ' . Str::random(4),
            'slug'          => 'ws-' . Str::random(8),
        ]);
        $link->workspace_id = $ws->id;
        $link->save();

        $viewer = $this->owner();
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $viewer->id,
            'role'         => 'viewer',
        ]);

        $html = (string) $this->actingAs($viewer)
            ->withSession([WorkspaceContext::SESSION_KEY => $ws->id])
            ->get(route('user.links.ics.tickets', $link, false))
            ->assertOk()
            ->getContent();

        $labels = array_column($this->tabs($html), 'label');

        $this->assertNotContains('Edit', $labels, 'A viewer should not be offered Edit');
        $this->assertContains('Tickets', $labels);
        $this->assertContains('RSVPs', $labels);
        $this->assertContains('Check-in', $labels);
    }

    public function test_a_link_that_is_not_an_event_gets_no_bar(): void
    {
        $user = $this->owner();
        $url  = $this->event($user, 'url');

        $this->assertSame(
            [],
            $this->tabsOn($user, route('user.links.show', $url, false)),
            'A plain URL link should not wear the event tab bar'
        );
    }

    public function test_switching_the_module_off_takes_the_bar_with_it(): void
    {
        $user = $this->owner();
        $link = $this->event($user);

        AppSetting::put(EventsModule::KEY, false);
        $this->assertFalse(EventsModule::enabled(), 'module switch did not take');

        // The six management routes 404 with the module off, so the event's
        // own Overview is the page that would otherwise render a bar full
        // of dead links.
        $this->assertSame(
            [],
            $this->tabsOn($user, route('user.links.show', $link, false)),
            'The bar should not survive the module being switched off'
        );
    }
}
