<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the rate-limiting and opt-out guardrails of
 * `users:send-email-verification-reminders`:
 *   - an unverified, opted-in user past the grace period gets exactly one
 *     email and has the counter + timestamp stamped;
 *   - a re-run inside REMINDER_INTERVAL_DAYS is skipped;
 *   - the MAX_REMINDERS cap is honoured;
 *   - verified users and users who opted out of the
 *     `email_verification_reminder` email channel are never emailed;
 *   - the command is a wholesale no-op when
 *     AuthMethods::emailVerificationMeaningful() is false.
 * Plus the signed one-click unsubscribe route flipping the email pref off.
 */
class SendEmailVerificationRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * An unverified user old enough to be past the grace period. Defaults
     * are chosen so the user is immediately eligible for a first reminder.
     */
    private function makeUnverifiedUser(array $overrides = []): User
    {
        // created_at/updated_at are not mass-assignable and Eloquent stamps
        // them to "now" on insert, so pull them out and force them after.
        $createdAt = $overrides['created_at'] ?? now()->subDays(30); // past grace period (3d)
        unset($overrides['created_at'], $overrides['updated_at']);

        $user = User::create(array_merge([
            'name'              => 'Creator '.Str::random(4),
            'email'             => 'c'.Str::random(8).'@e.com',
            'password'          => bcrypt('secret'),
            'email_verified_at' => null,
            'status'            => 'active',
        ], $overrides));

        $user->forceFill(['created_at' => $createdAt])->save();

        return $user->fresh();
    }

    /** Snapshot the in-memory array transport so we can count new sends. */
    private function mailBaseline(): int
    {
        return count(Mail::mailer()->getSymfonyTransport()->messages()->all());
    }

    private function mailDelta(int $baseline): int
    {
        return count(Mail::mailer()->getSymfonyTransport()->messages()->all()) - $baseline;
    }

    public function test_unverified_user_gets_one_email_and_counter_is_stamped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        $user = $this->makeUnverifiedUser();

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));

        $user->refresh();
        $this->assertSame(1, (int) $user->email_verification_reminders_sent);
        $this->assertNotNull($user->email_verification_reminder_sent_at);
        $this->assertTrue(now()->equalTo($user->email_verification_reminder_sent_at));
    }

    public function test_rerun_within_interval_is_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        // Already reminded 3 days ago — strictly inside REMINDER_INTERVAL_DAYS (7).
        $user = $this->makeUnverifiedUser([
            'email_verification_reminders_sent'   => 1,
            'email_verification_reminder_sent_at' => now()->subDays(3),
        ]);
        $stamp = $user->email_verification_reminder_sent_at->copy();

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));

        $user->refresh();
        $this->assertSame(1, (int) $user->email_verification_reminders_sent);
        $this->assertTrue($stamp->equalTo($user->email_verification_reminder_sent_at));
    }

    public function test_sends_again_after_the_interval_elapses(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        // Last reminder 8 days ago — older than REMINDER_INTERVAL_DAYS (7).
        $user = $this->makeUnverifiedUser([
            'email_verification_reminders_sent'   => 1,
            'email_verification_reminder_sent_at' => now()->subDays(8),
        ]);

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));

        $user->refresh();
        $this->assertSame(2, (int) $user->email_verification_reminders_sent);
    }

    public function test_max_reminders_cap_is_honoured(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        // Already at the MAX_REMINDERS (3) cap, last one long ago so the
        // interval is not what's blocking — only the cap should.
        $user = $this->makeUnverifiedUser([
            'email_verification_reminders_sent'   => 3,
            'email_verification_reminder_sent_at' => now()->subDays(60),
        ]);

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));

        $this->assertSame(3, (int) $user->fresh()->email_verification_reminders_sent);
    }

    public function test_grace_period_skips_freshly_signed_up_users(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        // Signed up 1 day ago — inside FIRST_REMINDER_AFTER_DAYS (3).
        $user = $this->makeUnverifiedUser([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));

        $this->assertSame(0, (int) $user->fresh()->email_verification_reminders_sent);
    }

    public function test_verified_users_are_never_emailed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        $this->makeUnverifiedUser([
            'email_verified_at' => now()->subMonth(),
        ]);

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));
    }

    public function test_users_who_opted_out_of_the_email_channel_are_never_emailed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        $user = $this->makeUnverifiedUser();

        NotificationPreference::create([
            'user_id' => $user->id,
            'type'    => 'email_verification_reminder',
            'in_app'  => false,
            'email'   => false,
            'push'    => false,
        ]);

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));

        $this->assertSame(0, (int) $user->fresh()->email_verification_reminders_sent);
    }

    public function test_command_is_a_noop_when_email_verification_is_not_meaningful(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        // Turn off both email login methods so email never authenticates an
        // account — emailVerificationMeaningful() becomes false.
        AppSetting::put(AuthMethods::SETTING_EMAIL_OTP_ENABLED, false);
        AppSetting::put(AuthMethods::SETTING_EMAIL_PASSWORD_ENABLED, false);
        $this->assertFalse(AuthMethods::emailVerificationMeaningful());

        $user = $this->makeUnverifiedUser();

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders')->assertExitCode(0);
        $this->assertSame(0, $this->mailDelta($before));

        $this->assertSame(0, (int) $user->fresh()->email_verification_reminders_sent);
    }

    public function test_force_flag_overrides_grace_interval_and_cap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00', 'UTC'));

        // Fresh sign-up, already at the cap, just reminded — every guardrail
        // except the opt-out should be bypassed by --force.
        $user = $this->makeUnverifiedUser([
            'created_at'                          => now(),
            'updated_at'                          => now(),
            'email_verification_reminders_sent'   => 3,
            'email_verification_reminder_sent_at' => now()->subHour(),
        ]);

        $before = $this->mailBaseline();
        $this->artisan('users:send-email-verification-reminders', ['--force' => true])->assertExitCode(0);
        $this->assertSame(1, $this->mailDelta($before));

        $this->assertSame(4, (int) $user->fresh()->email_verification_reminders_sent);
    }

    public function test_signed_unsubscribe_route_flips_the_email_preference_off(): void
    {
        $user = $this->makeUnverifiedUser();

        $url = URL::signedRoute(
            'user.notifications.email-verification-reminder.unsubscribe',
            ['user' => $user->id]
        );

        $this->get($url)->assertOk();

        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('type', 'email_verification_reminder')
            ->first();

        $this->assertNotNull($pref);
        $this->assertFalse((bool) $pref->email);
        $this->assertFalse((bool) $pref->in_app);
        $this->assertFalse((bool) $pref->push);

        // And the gate the command consults now reports opted-out.
        $this->assertFalse(
            app(\App\Modules\Common\Services\NotificationService::class)
                ->prefersChannel($user->id, 'email_verification_reminder', 'email')
        );
    }

    public function test_unsubscribe_route_rejects_an_unsigned_request(): void
    {
        $user = $this->makeUnverifiedUser();

        $this->get(route('user.notifications.email-verification-reminder.unsubscribe', ['user' => $user->id]))
            ->assertForbidden();

        $this->assertNull(
            NotificationPreference::where('user_id', $user->id)
                ->where('type', 'email_verification_reminder')
                ->first()
        );
    }
}
