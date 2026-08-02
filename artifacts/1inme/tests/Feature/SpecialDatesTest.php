<?php

namespace Tests\Feature;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\SpecialDateWishLog;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\SpecialDates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Task #6551 — Special Dates on the creator profile.
 *
 *  - editor CRUD + normalization (single kinds, product-release label rule);
 *  - public /@handle rendering respects per-entry visibility + the
 *    'special_dates' section toggle;
 *  - calendar lockstep: sync flag creates/updates/deletes the yearly all-day
 *    event on the public "Special Dates" calendar;
 *  - the wish command: once per occurrence (idempotent), preference opt-out,
 *    private dates never notify, creator heads-up.
 */
class SpecialDatesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    /** Bind a workspace + owner so workspace_owner()/can() resolve in user-module routes. */
    private function bind(User $u): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $ws;
    }

    private function unbind(): void
    {
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
    }

    private function saveDates(User $user, array $dates): \Illuminate\Testing\TestResponse
    {
        $this->bind($user);

        return $this->actingAs($user)->post(route('user.creator-profile.update'), [
            'special_dates' => $dates,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Editor CRUD + normalization ────────────────────────────────────────

    public function test_creator_can_save_and_update_special_dates(): void
    {
        $user = $this->makeUser(['handle' => 'sd-crud-' . uniqid()]);

        $this->saveDates($user, [
            ['kind' => 'birthday', 'date' => '1990-03-12', 'public' => '1', 'notify' => '1', 'sync' => '0'],
            ['kind' => 'product_release', 'label' => 'Zio 2.0', 'date' => '2024-06-01', 'public' => '1', 'notify' => '0', 'sync' => '0'],
        ])->assertRedirect();

        $user->refresh();
        $entries = SpecialDates::entries($user);
        $this->assertCount(2, $entries);
        $this->assertSame('birthday', $entries[0]['kind']);
        $this->assertTrue($entries[0]['public']);
        $this->assertSame('Zio 2.0', $entries[1]['label']);
        $this->assertNotSame('', $entries[0]['id']);

        // Update: keep ids, flip visibility, drop the release.
        $this->saveDates($user, [
            ['id' => $entries[0]['id'], 'kind' => 'birthday', 'date' => '1990-03-12', 'public' => '0', 'notify' => '0', 'sync' => '0'],
        ])->assertRedirect();

        $entries = SpecialDates::entries($user->refresh());
        $this->assertCount(1, $entries);
        $this->assertFalse($entries[0]['public']);
    }

    public function test_normalization_rules(): void
    {
        $user = $this->makeUser(['handle' => 'sd-norm-' . uniqid()]);

        $this->saveDates($user, [
            // duplicate single kind — second dropped
            ['kind' => 'birthday', 'date' => '1990-03-12'],
            ['kind' => 'birthday', 'date' => '1991-04-13'],
            // label-less product release — dropped
            ['kind' => 'product_release', 'label' => '', 'date' => '2024-06-01'],
        ])->assertRedirect();

        $entries = SpecialDates::entries($user->refresh());
        $this->assertCount(1, $entries);
        $this->assertSame('1990-03-12', $entries[0]['date']);
    }

    public function test_invalid_kind_or_date_rejected(): void
    {
        $user = $this->makeUser(['handle' => 'sd-bad-' . uniqid()]);

        $this->saveDates($user, [
            ['kind' => 'nonsense', 'date' => '1990-03-12'],
        ])->assertSessionHasErrors('special_dates.0.kind');

        $this->saveDates($user, [
            ['kind' => 'birthday', 'date' => '12/03/1990'],
        ])->assertSessionHasErrors('special_dates.0.date');
    }

    // ── Public profile rendering ───────────────────────────────────────────

    public function test_public_profile_shows_only_public_dates_and_respects_section_toggle(): void
    {
        $handle = 'sdpub' . uniqid();
        $user = $this->makeUser(['handle' => $handle, 'profile_published' => true]);

        $this->saveDates($user, [
            ['kind' => 'birthday', 'date' => '1990-03-12', 'public' => '1'],
            ['kind' => 'anniversary', 'date' => '2015-09-20', 'public' => '0'],
        ])->assertRedirect();

        $this->unbind();
        $res = $this->get('/@' . $handle);
        $res->assertOk();
        $res->assertSee('Special dates');
        $res->assertSee('Birthday');
        $res->assertSee('Mar 12');
        $res->assertDontSee('Sep 20');

        // Section toggled off → hidden even with public entries.
        $user->refresh();
        $vis = $user->profileSectionVisibility();
        $vis['special_dates'] = false;
        $user->forceFill(['profile_section_visibility' => $vis])->save();

        $this->get('/@' . $handle)->assertOk()->assertDontSee('Special dates');
    }

    // ── Calendar lockstep ──────────────────────────────────────────────────

    public function test_sync_flag_creates_updates_and_deletes_calendar_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));
        $user = $this->makeUser(['handle' => 'sdcal' . uniqid(), 'timezone' => 'UTC']);

        // Create with sync on.
        $this->saveDates($user, [
            ['kind' => 'birthday', 'date' => '1990-03-12', 'public' => '1', 'sync' => '1'],
        ])->assertRedirect();

        $entries = SpecialDates::entries($user->refresh());
        $this->assertNotNull($entries[0]['calendar_event_id']);

        $calendar = Calendar::where('user_id', $user->id)
            ->where('slug', SpecialDates::CALENDAR_SLUG_PREFIX . $user->id)->first();
        $this->assertNotNull($calendar);
        $this->assertTrue((bool) $calendar->is_public);

        $event = CalendarEvent::find($entries[0]['calendar_event_id']);
        $this->assertNotNull($event);
        $this->assertTrue((bool) $event->all_day);
        $this->assertSame('2026-03-12', $event->start_at->timezone('UTC')->format('Y-m-d'));

        // Edit the date → event moves.
        $this->saveDates($user, [
            ['id' => $entries[0]['id'], 'kind' => 'birthday', 'date' => '1990-05-01', 'public' => '1', 'sync' => '1'],
        ])->assertRedirect();

        $this->assertSame('2026-05-01', $event->refresh()->start_at->timezone('UTC')->format('Y-m-d'));

        // Sync off → event deleted, pointer cleared.
        $this->saveDates($user, [
            ['id' => $entries[0]['id'], 'kind' => 'birthday', 'date' => '1990-05-01', 'public' => '1', 'sync' => '0'],
        ])->assertRedirect();

        $this->assertNull(CalendarEvent::find($event->id));
        $this->assertNull(SpecialDates::entries($user->refresh())[0]['calendar_event_id']);
    }

    public function test_removing_entry_deletes_its_calendar_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));
        $user = $this->makeUser(['handle' => 'sdrm' . uniqid(), 'timezone' => 'UTC']);

        $this->saveDates($user, [
            ['kind' => 'anniversary', 'date' => '2015-09-20', 'sync' => '1'],
        ])->assertRedirect();

        $eventId = SpecialDates::entries($user->refresh())[0]['calendar_event_id'];
        $this->assertNotNull(CalendarEvent::find($eventId));

        // Submit an empty list (the editor's empty marker) — entry removed.
        $this->saveDates($user, [])->assertRedirect();

        $this->assertSame([], SpecialDates::entries($user->refresh()));
        $this->assertNull(CalendarEvent::find($eventId));
    }

    public function test_empty_marker_string_clears_all_entries_browser_form_semantics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));
        $user = $this->makeUser(['handle' => 'sdempty' . uniqid(), 'timezone' => 'UTC']);

        $this->saveDates($user, [
            ['kind' => 'birthday', 'date' => '1990-03-12', 'public' => '1', 'sync' => '1'],
        ])->assertRedirect();

        $eventId = SpecialDates::entries($user->refresh())[0]['calendar_event_id'];
        $this->assertNotNull($eventId);

        // A browser can't post an empty array — the editor submits the hidden
        // `special_dates=""` marker instead. It must clear entries + events.
        $this->bind($user);
        $this->actingAs($user)->post(route('user.creator-profile.update'), [
            'special_dates' => '',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame([], SpecialDates::entries($user->refresh()));
        $this->assertNull(CalendarEvent::find($eventId));
    }

    // ── Wish command ───────────────────────────────────────────────────────

    private function seedFollowedCreator(array $entryOverrides = []): array
    {
        Carbon::setTestNow(Carbon::parse('2026-03-12 09:30:00', 'UTC'));

        $creator  = $this->makeUser(['handle' => 'sdwish' . uniqid(), 'timezone' => 'UTC']);
        $follower = $this->makeUser();

        $entry = array_merge([
            'id'                => 'entry-' . uniqid(),
            'kind'              => 'birthday',
            'label'             => '',
            'date'              => '1990-03-12',
            'public'            => true,
            'notify'            => true,
            'sync'              => false,
            'calendar_event_id' => null,
        ], $entryOverrides);

        $creator->forceFill(['special_dates' => [$entry]])->save();

        Follow::create(['follower_id' => $follower->id, 'creator_id' => $creator->id, 'created_at' => now()]);

        return [$creator, $follower, $entry];
    }

    public function test_wish_command_notifies_followers_and_creator_once(): void
    {
        [$creator, $follower, $entry] = $this->seedFollowedCreator();

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        $followerNotes = UserNotification::where('user_id', $follower->id)->where('type', 'special_date_wish')->get();
        $this->assertCount(1, $followerNotes);
        $this->assertStringContainsString('birthday', $followerNotes->first()->data['title'] ?? '');

        $this->assertSame(1, UserNotification::where('user_id', $creator->id)->where('type', 'special_date_wish')->count());

        $this->assertDatabaseHas('special_date_wish_logs', [
            'user_id'         => $creator->id,
            'entry_id'        => $entry['id'],
            'occurrence_year' => 2026,
        ]);

        // Re-run: no duplicates.
        Artisan::call('special-dates:send-wishes', ['--force' => true]);
        $this->assertSame(1, UserNotification::where('user_id', $follower->id)->where('type', 'special_date_wish')->count());
        $this->assertSame(1, SpecialDateWishLog::where('user_id', $creator->id)->count());
    }

    public function test_private_dates_never_notify(): void
    {
        [$creator, $follower] = $this->seedFollowedCreator(['public' => false]);

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        $this->assertSame(0, UserNotification::where('user_id', $follower->id)->where('type', 'special_date_wish')->count());
    }

    public function test_notify_off_dates_do_not_notify(): void
    {
        [$creator, $follower] = $this->seedFollowedCreator(['notify' => false]);

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        $this->assertSame(0, UserNotification::where('user_id', $follower->id)->where('type', 'special_date_wish')->count());
    }

    public function test_follower_preference_opt_out_respected(): void
    {
        [$creator, $follower] = $this->seedFollowedCreator();

        NotificationPreference::create([
            'user_id' => $follower->id,
            'type'    => 'special_date_wish',
            'in_app'  => false,
            'email'   => false,
            'push'    => false,
        ]);

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        $this->assertSame(0, UserNotification::where('user_id', $follower->id)->where('type', 'special_date_wish')->count());
        // Occurrence is still claimed so a later pref flip doesn't re-send.
        $this->assertSame(1, SpecialDateWishLog::where('user_id', $creator->id)->count());
    }

    public function test_non_matching_day_sends_nothing(): void
    {
        [$creator, $follower] = $this->seedFollowedCreator(['date' => '1990-07-04']);

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        $this->assertSame(0, UserNotification::where('user_id', $follower->id)->count());
        $this->assertSame(0, SpecialDateWishLog::count());
    }

    public function test_wish_command_rolls_synced_event_forward(): void
    {
        [$creator, , $entry] = $this->seedFollowedCreator(['sync' => true]);

        // Mirror the event as the editor would.
        SpecialDates::syncCalendarEvents($creator->refresh());
        $entryNow = SpecialDates::entries($creator->refresh())[0];
        $event = CalendarEvent::find($entryNow['calendar_event_id']);
        $this->assertSame('2026-03-12', $event->start_at->timezone('UTC')->format('Y-m-d'));

        Artisan::call('special-dates:send-wishes', ['--force' => true]);

        $this->assertSame('2027-03-12', $event->refresh()->start_at->timezone('UTC')->format('Y-m-d'));
    }
}
