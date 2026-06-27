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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
        return User::create(array_merge([
            'name'     => 'Cal ' . Str::random(4),
            'email'    => 'cal' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
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
}
