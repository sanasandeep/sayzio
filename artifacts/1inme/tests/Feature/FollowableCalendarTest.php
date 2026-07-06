<?php

namespace Tests\Feature;

use App\Console\Commands\SendCalendarTodayReminders;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\CalendarIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the followable `calendar` link type — the surfaces
 * that have no other automated tests and could silently break:
 *
 *  - the follow / unfollow toggle on web (ViewerSession OR dashboard auth)
 *    and on the Sanctum REST API, including the followers_count book-keeping,
 *    self-follow and private-calendar guards;
 *  - the recipient-tz-aware "events today" reminder command — the 8-AM local
 *    gate, the per-recipient/per-event/per-day dedupe, and that the recipient
 *    set is exactly owners ∪ followers (no leak to unrelated users);
 *  - the per-plan event cap (`max_calendar_events`) on the web event-create
 *    flow;
 *  - the public ICS feed (open for public calendars, owner-only 404 gate for
 *    private ones).
 *
 * Authenticated API requests use a REAL personal access token, NOT
 * Sanctum::actingAs — the latter injects a mock that breaks the
 * TouchSessionToken middleware so every authed /api/v1 request would 500.
 */
class FollowableCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeCalendar(User $owner, array $attrs = []): Calendar
    {
        return Calendar::create(array_merge([
            'user_id'         => $owner->id,
            'title'           => 'Owner Calendar',
            'is_public'       => true,
            'followers_count' => 0,
            'timezone'        => 'UTC',
        ], $attrs));
    }

    /** Bind a workspace + owner so workspace_owner()/can() resolve in user-module routes. */
    private function bind(User $u): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $ws;
    }

    // ── Web follow / unfollow toggle ───────────────────────────────────────

    public function test_web_follow_toggle_creates_and_removes_follow_and_tracks_count(): void
    {
        $owner    = $this->makeUser();
        $follower = $this->makeUser();
        $cal      = $this->makeCalendar($owner);

        // Follow.
        $this->actingAs($follower)
            ->postJson(route('public.calendars.follow', $cal->id))
            ->assertOk()
            ->assertJson(['success' => true, 'following' => true, 'followers_count' => 1]);

        $this->assertDatabaseHas('calendar_follows', [
            'calendar_id' => $cal->id,
            'follower_id' => $follower->id,
        ]);
        $this->assertSame(1, (int) $cal->fresh()->followers_count);

        // Owner gets a "new follower" notification.
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $owner->id,
            'type'    => 'calendar.new_follower',
        ]);

        // Unfollow (toggle off).
        $this->actingAs($follower)
            ->postJson(route('public.calendars.follow', $cal->id))
            ->assertOk()
            ->assertJson(['success' => true, 'following' => false, 'followers_count' => 0]);

        $this->assertDatabaseMissing('calendar_follows', [
            'calendar_id' => $cal->id,
            'follower_id' => $follower->id,
        ]);
        $this->assertSame(0, (int) $cal->fresh()->followers_count);
    }

    public function test_web_follow_requires_a_signed_in_viewer(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner);

        $this->postJson(route('public.calendars.follow', $cal->id))
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_web_follow_rejects_self_follow_and_private_calendar(): void
    {
        $owner   = $this->makeUser();
        $public  = $this->makeCalendar($owner);
        $private = $this->makeCalendar($owner, ['is_public' => false, 'title' => 'Private']);

        // Owner can't follow their own calendar.
        $this->actingAs($owner)
            ->postJson(route('public.calendars.follow', $public->id))
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        // Nobody can follow a private calendar.
        $other = $this->makeUser();
        $this->actingAs($other)
            ->postJson(route('public.calendars.follow', $private->id))
            ->assertStatus(403)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('calendar_follows', 0);
    }

    // ── API follow / unfollow toggle ───────────────────────────────────────

    public function test_api_follow_toggle_creates_and_removes_follow(): void
    {
        $owner    = $this->makeUser();
        $follower = $this->makeUser();
        $cal      = $this->makeCalendar($owner);

        $this->withToken($this->token($follower))
            ->postJson("/api/v1/calendars/{$cal->id}/follow")
            ->assertOk()
            ->assertJsonPath('data.following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertDatabaseHas('calendar_follows', [
            'calendar_id' => $cal->id,
            'follower_id' => $follower->id,
        ]);

        // Toggle off.
        $this->withToken($this->token($follower))
            ->postJson("/api/v1/calendars/{$cal->id}/follow")
            ->assertOk()
            ->assertJsonPath('data.following', false)
            ->assertJsonPath('data.followers_count', 0);

        $this->assertDatabaseMissing('calendar_follows', [
            'calendar_id' => $cal->id,
            'follower_id' => $follower->id,
        ]);
    }

    public function test_api_follow_requires_authentication(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner);

        $this->postJson("/api/v1/calendars/{$cal->id}/follow")->assertStatus(401);
    }

    public function test_api_follow_rejects_self_follow_and_private_calendar(): void
    {
        $owner   = $this->makeUser();
        $private = $this->makeCalendar($owner, ['is_public' => false]);
        $public  = $this->makeCalendar($owner);

        $this->withToken($this->token($owner))
            ->postJson("/api/v1/calendars/{$public->id}/follow")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_follow_self');

        $other = $this->makeUser();
        $this->withToken($this->token($other))
            ->postJson("/api/v1/calendars/{$private->id}/follow")
            ->assertStatus(403);

        $this->assertDatabaseCount('calendar_follows', 0);
    }

    // ── Today-reminder command ─────────────────────────────────────────────

    public function test_today_reminder_notifies_owners_and_followers_but_not_others(): void
    {
        // Freeze at 08:00 UTC so a UTC-tz recipient is at their 8-AM local gate.
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00', 'UTC'));

        $owner     = $this->makeUser(['timezone' => 'UTC']);
        $follower  = $this->makeUser(['timezone' => 'UTC']);
        $bystander = $this->makeUser(['timezone' => 'UTC']);

        $cal = $this->makeCalendar($owner);
        CalendarFollow::create([
            'calendar_id' => $cal->id,
            'follower_id' => $follower->id,
            'created_at'  => now(),
        ]);

        // Event happening today (within the recipients' UTC day).
        $event = CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Launch party',
            'start_at'    => Carbon::parse('2026-06-27 18:00:00', 'UTC'),
            'timezone'    => 'UTC',
        ]);

        Artisan::call('calendars:send-today-reminders');

        // Owner + follower each get one reminder for the event.
        foreach ([$owner, $follower] as $recipient) {
            $this->assertSame(1, UserNotification::where('user_id', $recipient->id)
                ->where('type', 'calendar.event_today')
                ->where('data->event_id', $event->id)
                ->count());
        }

        // The bystander (neither owner nor follower) gets nothing.
        $this->assertSame(0, UserNotification::where('user_id', $bystander->id)
            ->where('type', 'calendar.event_today')
            ->count());

        Carbon::setTestNow();
    }

    public function test_today_reminder_is_deduped_per_recipient_per_event_per_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00', 'UTC'));

        $owner = $this->makeUser(['timezone' => 'UTC']);
        $cal   = $this->makeCalendar($owner);
        $event = CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Standup',
            'start_at'    => Carbon::parse('2026-06-27 10:00:00', 'UTC'),
            'timezone'    => 'UTC',
        ]);

        // Run twice in the same local day — the second run must be a no-op.
        Artisan::call('calendars:send-today-reminders');
        Artisan::call('calendars:send-today-reminders');

        $this->assertSame(1, UserNotification::where('user_id', $owner->id)
            ->where('type', 'calendar.event_today')
            ->where('data->event_id', $event->id)
            ->count());

        Carbon::setTestNow();
    }

    public function test_today_reminder_respects_recipient_local_8am_gate(): void
    {
        // 08:00 UTC == 04:00 in New York (UTC-4 in June) — NOT the 8-AM gate.
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00', 'UTC'));

        $owner = $this->makeUser(['timezone' => 'America/New_York']);
        $cal   = $this->makeCalendar($owner);
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Brunch',
            'start_at'    => Carbon::parse('2026-06-27 16:00:00', 'UTC'),
            'timezone'    => 'America/New_York',
        ]);

        // Without --force the gate suppresses the reminder.
        Artisan::call('calendars:send-today-reminders');
        $this->assertSame(0, UserNotification::where('user_id', $owner->id)
            ->where('type', 'calendar.event_today')
            ->count());

        // --force bypasses the hour gate.
        Artisan::call('calendars:send-today-reminders', ['--force' => true]);
        $this->assertSame(1, UserNotification::where('user_id', $owner->id)
            ->where('type', 'calendar.event_today')
            ->count());

        Carbon::setTestNow();
    }

    public function test_today_reminder_ignores_followers_of_private_calendars(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00', 'UTC'));

        $owner    = $this->makeUser(['timezone' => 'UTC']);
        $follower = $this->makeUser(['timezone' => 'UTC']);

        // Private calendar — the follower-side recipient query only joins public ones.
        $cal = $this->makeCalendar($owner, ['is_public' => false]);
        CalendarFollow::create([
            'calendar_id' => $cal->id,
            'follower_id' => $follower->id,
            'created_at'  => now(),
        ]);
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Private sync',
            'start_at'    => Carbon::parse('2026-06-27 12:00:00', 'UTC'),
            'timezone'    => 'UTC',
        ]);

        Artisan::call('calendars:send-today-reminders');

        // Owner still gets it (owners of ANY calendar are recipients)...
        $this->assertSame(1, UserNotification::where('user_id', $owner->id)
            ->where('type', 'calendar.event_today')
            ->count());
        // ...but the follower of a PRIVATE calendar does not.
        $this->assertSame(0, UserNotification::where('user_id', $follower->id)
            ->where('type', 'calendar.event_today')
            ->count());

        Carbon::setTestNow();
    }

    // ── Per-plan event cap ─────────────────────────────────────────────────

    private function makeCalendarLink(User $owner, ?int $calendarId = null): Link
    {
        return Link::create([
            'user_id'     => $owner->id,
            'type'        => Link::TYPE_CALENDAR,
            'alias'       => 'cal-' . Str::random(8),
            'title'       => 'Events',
            'calendar_id' => $calendarId,
        ]);
    }

    public function test_event_cap_blocks_creation_once_the_plan_limit_is_reached(): void
    {
        $plan  = Plan::create([
            'name'          => 'Capped',
            'slug'          => 'capped-' . Str::random(4),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['max_calendar_events' => 1],
        ]);
        $owner = $this->makeUser(['plan_id' => $plan->id]);
        $this->bind($owner);

        $link = $this->makeCalendarLink($owner);
        $cal  = Calendar::create([
            'user_id'   => $owner->id,
            'link_id'   => $link->id,
            'title'     => 'Events',
            'is_public' => true,
            'timezone'  => 'UTC',
        ]);
        // Already at the 1-event cap.
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'First',
            'start_at'    => now()->addDay(),
            'timezone'    => 'UTC',
        ]);

        $this->actingAs($owner)
            ->from(route('user.calendars.editor', $link->id))
            ->post(route('user.calendars.events.store', $link->id), [
                'title'    => 'Second',
                'start_at' => now()->addDays(2)->toDateTimeString(),
            ])
            ->assertSessionHas('error');

        // No new event was created.
        $this->assertSame(1, $cal->events()->count());
    }

    public function test_event_cap_allows_creation_when_under_the_limit(): void
    {
        $plan  = Plan::create([
            'name'          => 'Roomy',
            'slug'          => 'roomy-' . Str::random(4),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['max_calendar_events' => 5],
        ]);
        $owner = $this->makeUser(['plan_id' => $plan->id]);
        $this->bind($owner);

        $link = $this->makeCalendarLink($owner);
        Calendar::create([
            'user_id'   => $owner->id,
            'link_id'   => $link->id,
            'title'     => 'Events',
            'is_public' => true,
            'timezone'  => 'UTC',
        ]);

        $this->actingAs($owner)
            ->from(route('user.calendars.editor', $link->id))
            ->post(route('user.calendars.events.store', $link->id), [
                'title'    => 'Kickoff',
                'start_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Kickoff',
        ]);
    }

    public function test_editor_shows_locked_add_event_when_the_cap_is_reached(): void
    {
        $plan  = Plan::create([
            'name'          => 'Capped',
            'slug'          => 'capped-' . Str::random(4),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['max_calendar_events' => 1],
        ]);
        $owner = $this->makeUser(['plan_id' => $plan->id]);
        $this->bind($owner);

        $link = $this->makeCalendarLink($owner);
        $cal  = Calendar::create([
            'user_id'   => $owner->id,
            'link_id'   => $link->id,
            'title'     => 'Events',
            'is_public' => true,
            'timezone'  => 'UTC',
        ]);
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'First',
            'start_at'    => now()->addDay(),
            'timezone'    => 'UTC',
        ]);

        $res = $this->actingAs($owner)
            ->get(route('user.calendars.editor', $link->id))
            ->assertOk();

        $res->assertSee('1 / 1 event used');
        $res->assertSee('Event limit reached');
        // The plain add-event form should NOT be routed to from a locked state.
        $res->assertDontSee('<i class="fas fa-plus mr-1"></i> Add event', false);
    }

    public function test_editor_shows_remaining_count_when_under_the_cap(): void
    {
        $plan  = Plan::create([
            'name'          => 'Roomy',
            'slug'          => 'roomy-' . Str::random(4),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['max_calendar_events' => 5],
        ]);
        $owner = $this->makeUser(['plan_id' => $plan->id]);
        $this->bind($owner);

        $link = $this->makeCalendarLink($owner);
        $cal  = Calendar::create([
            'user_id'   => $owner->id,
            'link_id'   => $link->id,
            'title'     => 'Events',
            'is_public' => true,
            'timezone'  => 'UTC',
        ]);
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'First',
            'start_at'    => now()->addDay(),
            'timezone'    => 'UTC',
        ]);

        $res = $this->actingAs($owner)
            ->get(route('user.calendars.editor', $link->id))
            ->assertOk();

        $res->assertSee('1 / 5 events used');
        $res->assertSee('<i class="fas fa-plus mr-1"></i> Add event', false);
        $res->assertDontSee('Event limit reached');
    }

    public function test_editor_hides_quota_for_unlimited_plans(): void
    {
        $plan  = Plan::create([
            'name'          => 'Unlimited',
            'slug'          => 'unlimited-' . Str::random(4),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => ['max_calendar_events' => -1],
        ]);
        $owner = $this->makeUser(['plan_id' => $plan->id]);
        $this->bind($owner);

        $link = $this->makeCalendarLink($owner);
        Calendar::create([
            'user_id'   => $owner->id,
            'link_id'   => $link->id,
            'title'     => 'Events',
            'is_public' => true,
            'timezone'  => 'UTC',
        ]);

        $res = $this->actingAs($owner)
            ->get(route('user.calendars.editor', $link->id))
            ->assertOk();

        $res->assertDontSee('events used');
        $res->assertDontSee('event used');
        $res->assertSee('<i class="fas fa-plus mr-1"></i> Add event', false);
    }

    // ── ICS feed ───────────────────────────────────────────────────────────

    public function test_ics_feed_is_public_for_public_calendars(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner);
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Open Mic',
            'start_at'    => Carbon::parse('2026-07-01 19:00:00', 'UTC'),
            'timezone'    => 'UTC',
        ]);

        $res = $this->get(route('public.calendars.ics', $cal->id))->assertOk();
        $res->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $body = $res->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Open Mic', $body);
    }

    public function test_ics_feed_is_owner_only_for_private_calendars(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner, ['is_public' => false]);

        // Anonymous visitor — 404.
        $this->get(route('public.calendars.ics', $cal->id))->assertStatus(404);

        // A non-owner signed-in user — still 404.
        $other = $this->makeUser();
        $this->actingAs($other)
            ->get(route('public.calendars.ics', $cal->id))
            ->assertStatus(404);

        // The owner — 200.
        $this->actingAs($owner)
            ->get(route('public.calendars.ics', $cal->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }

    public function test_ics_feed_404s_for_a_missing_calendar(): void
    {
        $this->get(route('public.calendars.ics', 999999))->assertStatus(404);
    }

    // ── ICS date/time correctness ──────────────────────────────────────────

    public function test_ics_timed_event_uses_event_timezone_local_wall_clock(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner, ['timezone' => 'America/New_York']);

        // Stored in UTC; in July New York is UTC-4, so 19:00 UTC == 15:00 local.
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Evening Show',
            'start_at'    => Carbon::parse('2026-07-01 19:00:00', 'UTC'),
            'end_at'      => Carbon::parse('2026-07-01 21:30:00', 'UTC'),
            'timezone'    => 'America/New_York',
            'all_day'     => false,
        ]);

        $body = $this->get(route('public.calendars.ics', $cal->id))->assertOk()->getContent();

        // Local wall-clock in the event timezone, tagged with the TZID — NOT UTC.
        $this->assertStringContainsString('DTSTART;TZID=America/New_York:20260701T150000', $body);
        $this->assertStringContainsString('DTEND;TZID=America/New_York:20260701T173000', $body);
        // It must not be emitted as a bare UTC instant.
        $this->assertStringNotContainsString('DTSTART:20260701T190000Z', $body);
    }

    public function test_ics_all_day_event_uses_value_date_with_exclusive_dtend(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner);

        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Conference Day',
            'start_at'    => Carbon::parse('2026-07-01 00:00:00', 'UTC'),
            'timezone'    => 'UTC',
            'all_day'     => true,
        ]);

        $body = $this->get(route('public.calendars.ics', $cal->id))->assertOk()->getContent();

        // All-day events are VALUE=DATE and DTEND is exclusive (start + 1 day).
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260701', $body);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260702', $body);
        // No time component for an all-day event.
        $this->assertStringNotContainsString('DTSTART;TZID', $body);
    }

    public function test_ics_from_to_range_inclusively_filters_events(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner);

        $make = function (string $title, string $startUtc) use ($cal, $owner) {
            CalendarEvent::create([
                'calendar_id' => $cal->id,
                'user_id'     => $owner->id,
                'title'       => $title,
                'start_at'    => Carbon::parse($startUtc, 'UTC'),
                'timezone'    => 'UTC',
            ]);
        };

        $make('Before Window', '2026-07-01 12:00:00');   // before ?from — excluded
        $make('On From Boundary', '2026-07-05 00:30:00'); // same day as ?from — included
        $make('Inside Window', '2026-07-07 18:00:00');    // inside — included
        $make('On To Boundary', '2026-07-10 23:00:00');   // same day as ?to — included
        $make('After Window', '2026-07-12 09:00:00');     // after ?to — excluded

        $body = $this->get(route('public.calendars.ics', $cal->id) . '?from=2026-07-05&to=2026-07-10')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('SUMMARY:On From Boundary', $body);
        $this->assertStringContainsString('SUMMARY:Inside Window', $body);
        $this->assertStringContainsString('SUMMARY:On To Boundary', $body);

        $this->assertStringNotContainsString('SUMMARY:Before Window', $body);
        $this->assertStringNotContainsString('SUMMARY:After Window', $body);
    }

    public function test_calendar_ics_build_respects_explicit_from_to_bounds(): void
    {
        $owner = $this->makeUser();
        $cal   = $this->makeCalendar($owner);

        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'Out Of Range',
            'start_at'    => Carbon::parse('2026-08-01 10:00:00', 'UTC'),
            'timezone'    => 'UTC',
        ]);
        CalendarEvent::create([
            'calendar_id' => $cal->id,
            'user_id'     => $owner->id,
            'title'       => 'In Range',
            'start_at'    => Carbon::parse('2026-08-15 10:00:00', 'UTC'),
            'timezone'    => 'UTC',
        ]);

        // Reload with the events relation so build() iterates the full collection.
        $cal = Calendar::with('events')->find($cal->id);

        $ics = CalendarIcs::build(
            $cal,
            Carbon::parse('2026-08-10 00:00:00', 'UTC'),
            Carbon::parse('2026-08-20 23:59:59', 'UTC'),
        );

        $this->assertStringContainsString('SUMMARY:In Range', $ics);
        $this->assertStringNotContainsString('SUMMARY:Out Of Range', $ics);
    }
}
