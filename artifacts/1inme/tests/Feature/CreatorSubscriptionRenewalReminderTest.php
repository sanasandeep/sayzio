<?php

namespace Tests\Feature;

use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression lock for the renewal-reminder eligibility + once-per-period
 * dedup rules in SendCreatorSubscriptionRenewalReminders (Task #3013).
 *
 * The reminder must NEVER nag a fan about a charge that won't happen, so it
 * only fires for subscriptions that truly auto-charge: active, NOT set to
 * cancel at period end, price_cents > 0, with current_period_end inside the
 * lead window. The per-row `renewal_reminder_sent_at` stamp keeps it to at
 * most one reminder per billing period and re-arms automatically when the
 * period rolls forward.
 *
 * A reminder counts as "sent" when at least one channel is delivered; the
 * in-app channel (default_in_app = true for billing.creator_sub_renewal_reminder)
 * always writes a UserNotification, so these tests assert on that row plus the
 * stamp. Email is faked so no transport is exercised.
 */
class CreatorSubscriptionRenewalReminderTest extends TestCase
{
    use RefreshDatabase;

    private const TYPE = 'billing.creator_sub_renewal_reminder';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function makeTier(User $creator): SubscriptionTier
    {
        return SubscriptionTier::create([
            'user_id'             => $creator->id,
            'name'                => 'Gold',
            'slug'                => 't' . Str::lower(Str::random(8)),
            'price_monthly_cents' => 1000,
            'currency'            => 'USD',
            'is_active'           => true,
        ]);
    }

    /**
     * Build a subscription. Defaults describe an eligible, auto-charging,
     * not-yet-reminded sub whose renewal falls inside the default 3-day lead
     * window. Pass overrides to make it ineligible.
     */
    private function makeSub(User $creator, SubscriptionTier $tier, array $overrides = []): CreatorSubscription
    {
        $fan = $overrides['fan'] ?? $this->makeUser();
        unset($overrides['fan']);

        return CreatorSubscription::create(array_merge([
            'fan_user_id'              => $fan->id,
            'creator_user_id'          => $creator->id,
            'tier_id'                  => $tier->id,
            'billing_cycle'            => CreatorSubscription::CYCLE_MONTHLY,
            'status'                   => CreatorSubscription::STATUS_ACTIVE,
            'price_cents'              => 1000,
            'currency'                 => 'USD',
            'cancel_at_period_end'     => false,
            'current_period_start'     => now()->subDays(27),
            'current_period_end'       => now()->addDays(2),
            'renewal_reminder_sent_at' => null,
        ], $overrides));
    }

    private function runReminderCommand(array $options = []): void
    {
        Artisan::call('creator-subscriptions:send-renewal-reminders', $options);
    }

    private function reminderCount(int $fanId): int
    {
        return UserNotification::where('user_id', $fanId)
            ->where('type', self::TYPE)
            ->count();
    }

    /**
     * Email attempts can't be observed via Mail::fake() for this reminder: the
     * template format is `text`, so Emailer::dispatch sends via Mail::raw(),
     * which MailFake does not record. The reliable signal that the email
     * channel was exercised is the email_logs row Emailer writes on every send.
     */
    private function emailLogCount(string $recipient): int
    {
        return EmailLog::where('email_key', self::TYPE)
            ->where('recipient', $recipient)
            ->count();
    }

    private function setPrefs(int $userId, bool $inApp, bool $email): void
    {
        NotificationPreference::create([
            'user_id' => $userId,
            'type'    => self::TYPE,
            'in_app'  => $inApp,
            'email'   => $email,
            'push'    => false,
        ]);
    }

    public function test_eligible_active_subscription_gets_exactly_one_reminder_and_is_stamped(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier);

        $this->runReminderCommand();

        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));
        $this->assertNotNull($sub->fresh()->renewal_reminder_sent_at);
    }

    public function test_second_run_in_the_same_period_is_a_no_op(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier);

        $this->runReminderCommand();
        $firstStamp = $sub->fresh()->renewal_reminder_sent_at;
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));

        // Same billing period, run again: dedup must suppress a second send and
        // leave the stamp untouched.
        $this->runReminderCommand();

        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));
        $this->assertTrue($firstStamp->equalTo($sub->fresh()->renewal_reminder_sent_at));
    }

    public function test_rolled_forward_period_re_arms_the_reminder(): void
    {
        $this->travelTo(Carbon::parse('2026-07-01 12:00:00'));

        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier, [
            'current_period_start' => Carbon::parse('2026-06-04 12:00:00'),
            'current_period_end'   => Carbon::parse('2026-07-03 12:00:00'),
        ]);

        $this->runReminderCommand();
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));

        // Period rolls forward a month: current_period_start now post-dates the
        // first stamp, so the next run must re-arm and send again.
        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));
        $sub->forceFill([
            'current_period_start' => Carbon::parse('2026-07-04 12:00:00'),
            'current_period_end'   => Carbon::parse('2026-08-03 12:00:00'),
        ])->save();

        $this->runReminderCommand();

        $this->assertSame(2, $this->reminderCount($sub->fan_user_id));

        $this->travelBack();
    }

    /**
     * Every ineligible shape must send nothing and leave the stamp null. One
     * creator + tier is reused; each sub gets a distinct fan to satisfy the
     * (fan_user_id, creator_user_id) unique constraint.
     */
    public function test_ineligible_subscriptions_send_no_reminder(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);

        $cases = [
            'canceled'             => ['status' => CreatorSubscription::STATUS_CANCELED],
            'cancel_at_period_end' => ['cancel_at_period_end' => true],
            'trialing'             => ['status' => CreatorSubscription::STATUS_TRIALING],
            'past_due'             => ['status' => CreatorSubscription::STATUS_PAST_DUE],
            'paused'               => ['status' => CreatorSubscription::STATUS_PAUSED],
            'zero_price'           => ['price_cents' => 0],
            'out_of_window'        => ['current_period_end' => now()->addDays(30)],
            'already_past'         => ['current_period_end' => now()->subDay()],
        ];

        $subs = [];
        foreach ($cases as $label => $overrides) {
            $subs[$label] = $this->makeSub($creator, $tier, $overrides);
        }

        $this->runReminderCommand();

        foreach ($subs as $label => $sub) {
            $sub = $sub->fresh();
            $this->assertSame(0, $this->reminderCount($sub->fan_user_id), "expected no reminder for {$label} sub");
            $this->assertNull($sub->renewal_reminder_sent_at, "expected null stamp for {$label} sub");
        }
    }

    /**
     * --force ignores the once-per-period guard: a sub already reminded this
     * period is a no-op on a plain run, but --force re-sends on demand (the
     * manual re-send path support uses).
     */
    public function test_force_re_sends_an_already_reminded_subscription(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier);

        $this->runReminderCommand();
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));

        // Plain re-run in the same period is suppressed by the once-per-period
        // dedup guard.
        $this->runReminderCommand();
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));

        // --force ignores that guard and sends again on demand.
        $this->runReminderCommand(['--force' => true]);
        $this->assertSame(2, $this->reminderCount($sub->fan_user_id));
    }

    /**
     * --force also ignores the lead-time window: a sub whose renewal is far
     * outside the lead window (a plain no-op) is sent when forced.
     */
    public function test_force_ignores_the_lead_time_window(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier, [
            'current_period_end' => now()->addDays(30),
        ]);

        // Far outside the 3-day lead window: a plain run sends nothing.
        $this->runReminderCommand();
        $this->assertSame(0, $this->reminderCount($sub->fan_user_id));

        // --force ignores the window and sends on demand.
        $this->runReminderCommand(['--force' => true]);
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));
    }

    /**
     * --force still respects the hard eligibility filters baked into the base
     * query (status, cancel_at_period_end, price_cents). A canceled, set-to-lapse
     * or zero-price sub must NEVER be reminded — not even with --force.
     */
    public function test_force_still_respects_hard_eligibility_filters(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);

        $cases = [
            'canceled'             => ['status' => CreatorSubscription::STATUS_CANCELED],
            'cancel_at_period_end' => ['cancel_at_period_end' => true],
            'zero_price'           => ['price_cents' => 0],
        ];

        $subs = [];
        foreach ($cases as $label => $overrides) {
            $subs[$label] = $this->makeSub($creator, $tier, $overrides);
        }

        $this->runReminderCommand(['--force' => true]);

        foreach ($subs as $label => $sub) {
            $sub = $sub->fresh();
            $this->assertSame(0, $this->reminderCount($sub->fan_user_id), "expected no reminder for {$label} sub even with --force");
            $this->assertNull($sub->renewal_reminder_sent_at, "expected null stamp for {$label} sub even with --force");
        }
    }

    /**
     * --sub=<id> narrows the run to a single targeted subscription; other
     * otherwise-eligible subs are left untouched (manual single-sub re-send).
     */
    public function test_sub_option_only_reminds_the_targeted_subscription(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $target  = $this->makeSub($creator, $tier);
        $other   = $this->makeSub($creator, $tier);

        $this->runReminderCommand(['--sub' => $target->id]);

        $this->assertSame(1, $this->reminderCount($target->fan_user_id));
        $this->assertNotNull($target->fresh()->renewal_reminder_sent_at);

        $this->assertSame(0, $this->reminderCount($other->fan_user_id));
        $this->assertNull($other->fresh()->renewal_reminder_sent_at);
    }

    /**
     * A fan who muted BOTH channels (in_app + email) for this type must be left
     * completely alone: no in-app row, no email attempt. Because remind()
     * returns false when nothing was delivered, the stamp stays null — so this
     * sub is NOT marked as "reminded" and remains re-checkable. That is the
     * intended trade-off: a daily re-check of a fully-muted fan is cheap, and
     * we never want a delivered=false outcome to masquerade as a sent reminder.
     */
    public function test_fully_muted_fan_gets_nothing_and_is_not_stamped(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $fan     = $this->makeUser();
        $this->setPrefs($fan->id, inApp: false, email: false);

        $sub = $this->makeSub($creator, $tier, ['fan' => $fan]);

        $this->runReminderCommand();

        $this->assertSame(0, $this->reminderCount($fan->id), 'fully-muted fan must get no in-app row');
        $this->assertSame(0, $this->emailLogCount($fan->email), 'fully-muted fan must get no email attempt');
        $this->assertNull($sub->fresh()->renewal_reminder_sent_at, 'nothing delivered ⇒ stamp stays null (not suppressed)');
    }

    /**
     * A fan who muted only the in-app channel but kept email on must still get
     * the email heads-up. No in-app row is written, but the email channel is
     * attempted (an email_logs row appears) and — because at least one channel
     * was delivered — the once-per-period stamp IS set so the next daily run
     * won't email them again for this billing period.
     */
    public function test_in_app_muted_email_on_emails_and_stamps(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $fan     = $this->makeUser();
        $this->setPrefs($fan->id, inApp: false, email: true);

        $sub = $this->makeSub($creator, $tier, ['fan' => $fan]);

        $this->runReminderCommand();

        $this->assertSame(0, $this->reminderCount($fan->id), 'in-app muted ⇒ no in-app row');
        $this->assertSame(1, $this->emailLogCount($fan->email), 'email on ⇒ exactly one email attempt');
        $this->assertNotNull($sub->fresh()->renewal_reminder_sent_at, 'email delivered ⇒ stamp is set');
    }
}
