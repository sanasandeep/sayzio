<?php

namespace Tests\Feature;

use App\Mail\EventRsvpConfirmationMail;
use App\Mail\EventRsvpNotifyOwnerMail;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end coverage for the "Smarter event invites" overhaul:
 *   - recurrence variants (every-weekday, monthly weekday-ordinal, yearly month, multi-slot, cross-midnight)
 *   - calendar sync mode (off / one_time / keep_in_sync) push + push-delete
 *   - richer RSVP (capacity + waitlist, deadline, custom questions,
 *     per-occurrence, manage-token edit + cancel)
 */
class SmartEventInvitesTest extends TestCase
{
    use RefreshDatabase;

    // ---------- helpers ----------

    private function makeUser(): User
    {
        $u = User::factory()->create();
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function makeIcsLink(User $user, array $settings = [], array $extra = []): Link
    {
        // Deterministic safe-prefix alias — the public alias regex
        // (`^(?!user|admin|qr|storage|sanctum|api|f|webhooks).*$`) blocks
        // anything starting with `f`, so a random Str::random(7) intermittently
        // 404s. Prefixing with "evt" keeps the alias inside the allowed set.
        return Link::create(array_merge([
            'user_id'  => $user->id,
            'type'     => 'ics',
            'alias'    => 'evt' . Str::random(8),
            'title'    => 'Launch Party',
            'settings' => $settings,
        ], $extra));
    }

    protected function tearDown(): void
    {
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }
        parent::tearDown();
    }

    private function makeIcsData(Link $link, array $overrides = []): IcsData
    {
        return IcsData::create(array_merge([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-06-01 09:00:00',
            'end_date'   => '2030-06-01 10:00:00',
            'timezone'   => 'UTC',
            'all_day'    => false,
        ], $overrides));
    }

    // ---------- IcsData / toIcs / RRULE ----------

    public function test_cross_midnight_slot_rolls_dtend_forward_to_next_day(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user);

        // Slot starts 21:00, ends 01:00 same day → must roll DTEND to next day.
        $ics = $this->makeIcsData($link, [
            'slots' => [[
                'start' => '2030-06-01 21:00:00',
                'end'   => '2030-06-01 01:00:00',
            ]],
        ]);

        $out = $ics->toIcs();
        $this->assertStringContainsString('DTSTART;TZID=UTC:20300601T210000', $out);
        $this->assertStringContainsString('DTEND;TZID=UTC:20300602T010000', $out);
    }

    public function test_multi_slot_emits_one_vevent_each_with_rrule_only_on_first(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user);

        $ics = $this->makeIcsData($link, [
            'recurrence_freq'     => 'weekly',
            'recurrence_interval' => 1,
            'slots' => [
                ['start' => '2030-06-01 09:00:00', 'end' => '2030-06-01 10:00:00', 'label' => 'Morning'],
                ['start' => '2030-06-01 14:00:00', 'end' => '2030-06-01 15:00:00', 'label' => 'Afternoon'],
                ['start' => '2030-06-01 18:00:00', 'end' => '2030-06-01 19:00:00'],
            ],
        ]);

        $out = $ics->toIcs();

        // 3 VEVENTs.
        $this->assertSame(3, substr_count($out, 'BEGIN:VEVENT'));
        $this->assertSame(3, substr_count($out, 'END:VEVENT'));

        // RRULE on the first VEVENT only.
        $this->assertSame(1, substr_count($out, "RRULE:FREQ=WEEKLY"));

        // Per-slot labels become SUMMARY; third slot falls back to the event name.
        $this->assertStringContainsString('SUMMARY:Morning', $out);
        $this->assertStringContainsString('SUMMARY:Afternoon', $out);
        $this->assertStringContainsString('SUMMARY:Launch Party', $out);
    }

    public function test_weekdays_freq_normalizes_to_weekly_with_byday_mo_to_fr(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user);
        $this->makeIcsData($link);

        $this->actingAs($user)->put('/user/links-ics/' . $link->id, [
            'event_name'      => 'Standup',
            'start_date'      => '2030-06-03 09:00:00',
            'end_date'        => '2030-06-03 09:30:00',
            'timezone'        => 'UTC',
            'recurrence_freq' => 'weekdays',
        ])->assertRedirect();

        $ics = $link->fresh('icsData')->icsData;
        $this->assertSame('weekly', $ics->recurrence_freq);
        $this->assertSame('MO,TU,WE,TH,FR', $ics->recurrence_byday);

        $rrule = $this->extractRrule($ics->toIcs());
        $this->assertStringContainsString('FREQ=WEEKLY', $rrule);
        $this->assertStringContainsString('BYDAY=MO,TU,WE,TH,FR', $rrule);
    }

    public function test_monthly_weekday_ordinal_emits_byday_with_ordinal(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user);
        $this->makeIcsData($link);

        // 2030-06-11 is the 2nd Tuesday → expect BYDAY=2TU.
        $this->actingAs($user)->put('/user/links-ics/' . $link->id, [
            'event_name'              => 'Monthly board',
            'start_date'              => '2030-06-11 09:00:00',
            'end_date'                => '2030-06-11 10:00:00',
            'timezone'                => 'UTC',
            'recurrence_freq'         => 'monthly',
            'monthly_mode'            => 'weekday_ordinal',
            'monthly_weekday_ordinal' => '2',
            'recurrence_byday'        => ['TU'],
        ])->assertRedirect();

        $ics = $link->fresh('icsData')->icsData;
        $this->assertSame('weekday_ordinal', $ics->monthly_mode);
        $this->assertSame('2', $ics->monthly_weekday_ordinal);

        $rrule = $this->extractRrule($ics->toIcs());
        $this->assertStringContainsString('FREQ=MONTHLY', $rrule);
        $this->assertStringContainsString('BYDAY=2TU', $rrule);
    }

    public function test_yearly_emits_bymonth_and_bymonthday(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user);
        $this->makeIcsData($link);

        $this->actingAs($user)->put('/user/links-ics/' . $link->id, [
            'event_name'      => 'Annual gala',
            'start_date'      => '2030-09-15 18:00:00',
            'end_date'        => '2030-09-15 22:00:00',
            'timezone'        => 'UTC',
            'recurrence_freq' => 'yearly',
            'yearly_month'    => 9,
        ])->assertRedirect();

        $ics = $link->fresh('icsData')->icsData;
        $this->assertSame(9, (int) $ics->yearly_month);

        $rrule = $this->extractRrule($ics->toIcs());
        $this->assertStringContainsString('FREQ=YEARLY', $rrule);
        $this->assertStringContainsString('BYMONTH=9', $rrule);
        $this->assertStringContainsString('BYMONTHDAY=15', $rrule);
    }

    public function test_end_more_than_36_hours_after_start_is_rejected(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user);
        $this->makeIcsData($link);

        $this->actingAs($user)->put('/user/links-ics/' . $link->id, [
            'event_name' => 'Too long',
            'start_date' => '2030-06-01 09:00:00',
            'end_date'   => '2030-06-05 09:00:00', // 96 h
            'timezone'   => 'UTC',
        ])->assertSessionHasErrors('end_date');
    }

    private function extractRrule(string $ics): string
    {
        if (preg_match('/RRULE:([^\r\n]+)/', $ics, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    // ---------- Calendar sync (push + delete) ----------

    public function test_keep_in_sync_pushes_link_on_update(): void
    {
        $user = $this->makeUser();
        $account = CalendarAccount::create([
            'user_id'      => $user->id,
            'provider'     => 'google',
            'display_name' => 'Work',
            'account_email'=> 'work@ex.com',
            'push_enabled' => true,
        ]);
        $link = $this->makeIcsLink($user);
        $this->makeIcsData($link);

        $mock = Mockery::mock(CalendarSyncService::class);
        $mock->shouldReceive('pushLink')
            ->once()
            ->withArgs(fn ($acc, $lnk) => $acc->id === $account->id && $lnk->id === $link->id);
        $this->app->instance(CalendarSyncService::class, $mock);

        $this->actingAs($user)->put('/user/links-ics/' . $link->id, [
            'event_name'              => 'Synced event',
            'start_date'              => '2030-06-01 09:00:00',
            'end_date'                => '2030-06-01 10:00:00',
            'timezone'                => 'UTC',
            'calendar_sync_mode'      => 'keep_in_sync',
            'push_calendar_account_id'=> $account->id,
        ])->assertRedirect();

        $fresh = $link->fresh();
        $this->assertSame('keep_in_sync', $fresh->settings['calendar_sync_mode'] ?? null);
        $this->assertSame($account->id, $fresh->settings['push_calendar_account_id'] ?? null);
    }

    public function test_off_mode_does_not_push_to_calendar(): void
    {
        $user = $this->makeUser();
        $account = CalendarAccount::create([
            'user_id'      => $user->id,
            'provider'     => 'google',
            'display_name' => 'Work',
            'account_email'=> 'work@ex.com',
            'push_enabled' => true,
        ]);
        $link = $this->makeIcsLink($user);
        $this->makeIcsData($link);

        $mock = Mockery::mock(CalendarSyncService::class);
        $mock->shouldNotReceive('pushLink');
        $this->app->instance(CalendarSyncService::class, $mock);

        $this->actingAs($user)->put('/user/links-ics/' . $link->id, [
            'event_name'              => 'Untracked',
            'start_date'              => '2030-06-01 09:00:00',
            'end_date'                => '2030-06-01 10:00:00',
            'timezone'                => 'UTC',
            'calendar_sync_mode'      => 'off',
            'push_calendar_account_id'=> $account->id,
        ])->assertRedirect();
    }

    public function test_keep_in_sync_ignores_account_owned_by_another_user(): void
    {
        $owner = $this->makeUser();
        $stranger = User::create([
            'name' => 'X', 'email' => 'x' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);
        $foreignAccount = CalendarAccount::create([
            'user_id'      => $stranger->id,
            'provider'     => 'google',
            'display_name' => 'Foreign',
            'account_email'=> 'foreign@ex.com',
            'push_enabled' => true,
        ]);
        $link = $this->makeIcsLink($owner);
        $this->makeIcsData($link);

        $mock = Mockery::mock(CalendarSyncService::class);
        $mock->shouldNotReceive('pushLink');
        $this->app->instance(CalendarSyncService::class, $mock);

        $this->actingAs($owner)->put('/user/links-ics/' . $link->id, [
            'event_name'              => 'Owned',
            'start_date'              => '2030-06-01 09:00:00',
            'end_date'                => '2030-06-01 10:00:00',
            'timezone'                => 'UTC',
            'calendar_sync_mode'      => 'keep_in_sync',
            'push_calendar_account_id'=> $foreignAccount->id,
        ])->assertRedirect();

        // Foreign account id was rejected, so the setting must not bind to it.
        $fresh = $link->fresh();
        $this->assertArrayNotHasKey('push_calendar_account_id', (array) $fresh->settings);
    }

    public function test_link_delete_with_keep_in_sync_calls_delete_pushed_link(): void
    {
        $user = $this->makeUser();
        $account = CalendarAccount::create([
            'user_id'      => $user->id,
            'provider'     => 'google',
            'display_name' => 'Work',
            'account_email'=> 'work@ex.com',
            'push_enabled' => true,
        ]);
        $link = $this->makeIcsLink($user, [
            'calendar_sync_mode'       => 'keep_in_sync',
            'push_calendar_account_id' => $account->id,
        ]);
        $this->makeIcsData($link);

        $mock = Mockery::mock(CalendarSyncService::class);
        $mock->shouldReceive('deletePushedLink')
            ->once()
            ->withArgs(fn ($acc, $lnk) => $acc->id === $account->id && $lnk->id === $link->id);
        $this->app->instance(CalendarSyncService::class, $mock);

        $this->actingAs($user)->delete('/user/links/' . $link->id)->assertRedirect();

        $this->assertNull(Link::find($link->id));
    }

    // ---------- RSVP: capacity / waitlist / deadline ----------

    public function test_capacity_full_promotes_new_rsvp_to_waitlist_when_enabled(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, [
            'rsvp_enabled'         => true,
            'rsvp_allow_plus_ones' => true,
            'rsvp_settings'        => [
                'capacity'         => 2,
                'waitlist_enabled' => true,
            ],
        ]);
        $this->makeIcsData($link);

        // Seat 1 + plus-one = 2 → capacity hit.
        Rsvp::create([
            'link_id' => $link->id, 'name' => 'A', 'email' => 'a@ex.com',
            'response' => 'yes', 'plus_ones' => 1, 'status' => 'confirmed',
        ]);

        $resp = $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'B', 'email' => 'b@ex.com', 'response' => 'yes', 'plus_ones' => 0,
        ]);
        $resp->assertOk();

        $b = Rsvp::where('email', 'b@ex.com')->firstOrFail();
        $this->assertSame('waitlist', $b->status);
    }

    public function test_capacity_full_returns_422_when_waitlist_disabled(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, [
            'rsvp_enabled'  => true,
            'rsvp_settings' => ['capacity' => 1, 'waitlist_enabled' => false],
        ]);
        $this->makeIcsData($link);

        Rsvp::create([
            'link_id' => $link->id, 'name' => 'A', 'email' => 'a@ex.com',
            'response' => 'yes', 'status' => 'confirmed',
        ]);

        $resp = $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'B', 'email' => 'b@ex.com', 'response' => 'yes',
        ]);
        $resp->assertStatus(422);
        $this->assertSame('This event is full.', $resp->json('message'));
        $this->assertSame(0, Rsvp::where('email', 'b@ex.com')->count());
    }

    public function test_deadline_in_past_blocks_submission(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, [
            'rsvp_enabled'  => true,
            'rsvp_settings' => ['deadline' => '2000-01-01 00:00:00'],
        ]);
        $this->makeIcsData($link);

        $resp = $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'Late', 'email' => 'late@ex.com', 'response' => 'yes',
        ]);
        $resp->assertStatus(422);
        $this->assertSame('RSVPs are closed for this event.', $resp->json('message'));
        $this->assertSame(0, Rsvp::where('link_id', $link->id)->count());
    }

    // ---------- RSVP: custom questions, per-occurrence ----------

    public function test_required_custom_question_validation_and_round_trip(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, [
            'rsvp_enabled'  => true,
            'rsvp_settings' => [
                'questions' => [
                    ['label' => 'Dietary', 'type' => 'text', 'required' => true, 'options' => []],
                    ['label' => 'Notes',   'type' => 'text', 'required' => false, 'options' => []],
                ],
            ],
        ]);
        $this->makeIcsData($link);

        // Missing the required answer → 422 with a per-question error key.
        $missing = $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'Q', 'email' => 'q@ex.com', 'response' => 'yes',
            'answers' => ['Notes' => 'just notes'],
        ]);
        $missing->assertStatus(422);
        $errs = (array) $missing->json('errors');
        $this->assertNotEmpty($errs['answers.Dietary'] ?? null);
        $this->assertSame(0, Rsvp::where('link_id', $link->id)->count());

        // Now provide the required answer → persisted in `answers`.
        $ok = $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'Q', 'email' => 'q@ex.com', 'response' => 'yes',
            'answers' => ['Dietary' => 'Vegan', 'Notes' => 'No nuts'],
        ]);
        $ok->assertOk();

        $rsvp = Rsvp::where('email', 'q@ex.com')->firstOrFail();
        $this->assertSame('Vegan',   $rsvp->answers['Dietary']);
        $this->assertSame('No nuts', $rsvp->answers['Notes']);
    }

    public function test_per_occurrence_rsvp_persists_selected_occurrences(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, [
            'rsvp_enabled'  => true,
            'rsvp_settings' => ['per_occurrence' => true],
        ]);
        $this->makeIcsData($link);

        $resp = $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'Multi', 'email' => 'multi@ex.com', 'response' => 'yes',
            'occurrences' => ['2030-06-01T09:00:00+00:00', '2030-06-08T09:00:00+00:00'],
        ]);
        $resp->assertOk();

        $rsvp = Rsvp::where('email', 'multi@ex.com')->firstOrFail();
        $this->assertSame(
            ['2030-06-01T09:00:00+00:00', '2030-06-08T09:00:00+00:00'],
            $rsvp->occurrences,
        );
    }

    public function test_confirmation_and_owner_notify_emails_sent(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, [
            'rsvp_enabled'  => true,
            'rsvp_settings' => ['send_confirmation' => true, 'notify_owner' => true],
        ]);
        $this->makeIcsData($link);

        $this->postJson('/' . $link->alias . '/rsvp', [
            'name' => 'Mailme', 'email' => 'mailme@ex.com', 'response' => 'yes',
        ])->assertOk();

        Mail::assertSent(EventRsvpConfirmationMail::class, fn ($m) => $m->hasTo('mailme@ex.com'));
        Mail::assertSent(EventRsvpNotifyOwnerMail::class,  fn ($m) => $m->hasTo($user->email));
    }

    // ---------- Manage-token edit + cancel ----------

    public function test_manage_token_update_changes_rsvp_response_and_fields(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, ['rsvp_enabled' => true, 'rsvp_allow_plus_ones' => true]);
        $this->makeIcsData($link);

        $rsvp = Rsvp::create([
            'link_id' => $link->id, 'name' => 'Old', 'email' => 'g@ex.com',
            'response' => 'maybe', 'plus_ones' => 0, 'status' => 'confirmed',
        ]);
        $this->assertNotEmpty($rsvp->manage_token);

        $resp = $this->post(
            '/' . $link->alias . '/rsvp/manage/' . $rsvp->manage_token,
            ['name' => 'New Name', 'response' => 'yes', 'plus_ones' => 3]
        );
        $resp->assertRedirect();

        $rsvp->refresh();
        $this->assertSame('New Name', $rsvp->name);
        $this->assertSame('yes',      $rsvp->response);
        $this->assertSame(3,          $rsvp->plus_ones);
    }

    public function test_manage_token_cancel_marks_rsvp_cancelled(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, ['rsvp_enabled' => true]);
        $this->makeIcsData($link);

        $rsvp = Rsvp::create([
            'link_id' => $link->id, 'name' => 'Bye', 'email' => 'bye@ex.com',
            'response' => 'yes', 'status' => 'confirmed',
        ]);

        $this->post('/' . $link->alias . '/rsvp/manage/' . $rsvp->manage_token . '/cancel')
            ->assertRedirect();

        $rsvp->refresh();
        $this->assertSame('cancelled', $rsvp->status);
        $this->assertSame('no',        $rsvp->response);
    }

    public function test_manage_token_invalid_returns_404(): void
    {
        $user = $this->makeUser();
        $link = $this->makeIcsLink($user, ['rsvp_enabled' => true]);
        $this->makeIcsData($link);

        $this->get('/' . $link->alias . '/rsvp/manage/' . str_repeat('z', 40))
            ->assertNotFound();
    }
}
