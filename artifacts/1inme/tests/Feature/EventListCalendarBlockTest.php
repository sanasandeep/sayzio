<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6615 — Smart Calendar as a biolink block.
 *
 * Covers the calendar-sourced `event_list` block end to end:
 * editor save/sanitize round-trip, live public rendering from the
 * calendar's events (incl. the view/subscribe footer), the empty-calendar
 * state, and fail-closed behaviour for deleted/foreign source links.
 */
class EventListCalendarBlockTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($u);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function biolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => 'zb' . Str::lower(Str::random(10)),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    /** @return array{0: Link, 1: Calendar} */
    private function calendarLink(User $owner, string $title = 'Studio Calendar'): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => Link::TYPE_CALENDAR,
            'alias'     => 'zc' . Str::lower(Str::random(10)),
            'title'     => $title,
            'is_active' => true,
        ]);

        $calendar = Calendar::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'title'    => $title,
            'timezone' => 'UTC',
        ]);

        $link->forceFill(['calendar_id' => $calendar->id])->save();

        return [$link, $calendar];
    }

    private function event(Calendar $cal, string $title, int $daysAhead = 3): CalendarEvent
    {
        return CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $cal->user_id,
            'title'       => $title,
            'start_at'    => now()->addDays($daysAhead),
            'timezone'    => 'UTC',
            'location'    => 'Main Hall',
        ]);
    }

    private function visitPublic(string $alias)
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        return $this->get('/' . $alias);
    }

    // ── Editor save / sanitize round-trip ───────────────────────────

    public function test_update_sanitizes_calendar_source_settings(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);
        [$calLink] = $this->calendarLink($owner);

        $block = BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => [],
            'is_active' => true,
        ]);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$page->id}/blocks/{$block->id}", [
                'settings' => [
                    'title'            => 'Upcoming shows',
                    'calendar_link_id' => (string) $calLink->id,
                    'count'            => '99',
                    'range_days'       => '9999',
                    'layout'           => 'bogus-layout',
                    'show_subscribe'   => '1',
                ],
            ]);
        $resp->assertOk();

        $s = $block->fresh()->settings;
        $this->assertSame($calLink->id, $s['calendar_link_id']);
        $this->assertSame(20, $s['count'], 'count clamps to the max');
        $this->assertSame(365, $s['range_days'], 'range_days clamps to the max');
        $this->assertSame('compact', $s['layout'], 'unknown layout falls back to compact');
        $this->assertTrue($s['show_subscribe']);
    }

    public function test_update_drops_empty_calendar_source(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);

        $block = BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => ['calendar_link_id' => 123],
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$page->id}/blocks/{$block->id}", [
                'settings' => ['calendar_link_id' => '', 'range_days' => ''],
            ])->assertOk();

        $s = $block->fresh()->settings;
        $this->assertArrayNotHasKey('calendar_link_id', $s);
        $this->assertArrayNotHasKey('range_days', $s);
    }

    // ── Public rendering ─────────────────────────────────────────────

    public function test_public_page_renders_live_calendar_events_with_footer(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);
        [$calLink, $calendar] = $this->calendarLink($owner);
        $this->event($calendar, 'Vinyl Listening Night');
        $this->event($calendar, 'Rooftop Acoustic Set', 5);

        BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => ['calendar_link_id' => $calLink->id],
            'is_active' => true,
        ]);

        $resp = $this->visitPublic($page->alias);
        $resp->assertOk();
        $resp->assertSee('Vinyl Listening Night');
        $resp->assertSee('Rooftop Acoustic Set');
        $resp->assertSee('View full calendar');
        $resp->assertSee('/' . $calLink->alias);
        $resp->assertSee(route('public.calendars.ics', $calendar->id), false);
    }

    public function test_count_limits_rendered_events_and_subscribe_can_be_hidden(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);
        [$calLink, $calendar] = $this->calendarLink($owner);
        $this->event($calendar, 'First Upcoming Event', 1);
        $this->event($calendar, 'Second Upcoming Event', 2);

        BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => [
                'calendar_link_id' => $calLink->id,
                'count'            => 1,
                'show_subscribe'   => false,
            ],
            'is_active' => true,
        ]);

        $resp = $this->visitPublic($page->alias);
        $resp->assertOk();
        $resp->assertSee('First Upcoming Event');
        $resp->assertDontSee('Second Upcoming Event');
        $resp->assertDontSee('View full calendar');
    }

    public function test_empty_calendar_renders_no_upcoming_events_state(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);
        [$calLink, $calendar] = $this->calendarLink($owner);
        // Only a PAST event — upcoming feed is empty.
        CalendarEvent::create([
            'calendar_id' => $calendar->id,
            'user_id'     => $owner->id,
            'title'       => 'Old Gathering',
            'start_at'    => now()->subDays(3),
            'timezone'    => 'UTC',
        ]);

        BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => ['calendar_link_id' => $calLink->id],
            'is_active' => true,
        ]);

        $resp = $this->visitPublic($page->alias);
        $resp->assertOk();
        $resp->assertSee('No upcoming events');
        $resp->assertDontSee('Old Gathering');
    }

    public function test_deleted_source_link_falls_back_to_manual_events(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);
        [$calLink, $calendar] = $this->calendarLink($owner);
        $this->event($calendar, 'Ghost Event');
        $calLinkId = $calLink->id;
        $calLink->delete();

        BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => [
                'calendar_link_id' => $calLinkId,
                'events'           => [['title' => 'Manual Backup Event', 'date' => 'Aug 20']],
            ],
            'is_active' => true,
        ]);

        $resp = $this->visitPublic($page->alias);
        $resp->assertOk();
        $resp->assertDontSee('Ghost Event');
        $resp->assertSee('Manual Backup Event');
        $resp->assertDontSee('View full calendar');
    }

    public function test_foreign_calendar_link_is_not_rendered(): void
    {
        $owner = $this->owner();
        $page  = $this->biolink($owner);

        $other = User::create([
            'name'     => 'Other ' . Str::random(4),
            'email'    => 'oth' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        [$foreignLink, $foreignCal] = $this->calendarLink($other, 'Foreign Calendar');
        $this->event($foreignCal, 'Private Foreign Event');

        BiolinkBlock::create([
            'link_id'   => $page->id,
            'type'      => 'event_list',
            'settings'  => ['calendar_link_id' => $foreignLink->id],
            'is_active' => true,
        ]);

        $resp = $this->visitPublic($page->alias);
        $resp->assertOk();
        $resp->assertDontSee('Private Foreign Event');
        $resp->assertDontSee('View full calendar');
    }
}
