<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
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
        return User::create(array_merge([
            'name'     => 'U' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
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

    private function run(array $options = []): void
    {
        Artisan::call('creator-subscriptions:send-renewal-reminders', $options);
    }

    private function reminderCount(int $fanId): int
    {
        return UserNotification::where('user_id', $fanId)
            ->where('type', self::TYPE)
            ->count();
    }

    public function test_eligible_active_subscription_gets_exactly_one_reminder_and_is_stamped(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier);

        $this->run();

        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));
        $this->assertNotNull($sub->fresh()->renewal_reminder_sent_at);
    }

    public function test_second_run_in_the_same_period_is_a_no_op(): void
    {
        $creator = $this->makeUser();
        $tier    = $this->makeTier($creator);
        $sub     = $this->makeSub($creator, $tier);

        $this->run();
        $firstStamp = $sub->fresh()->renewal_reminder_sent_at;
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));

        // Same billing period, run again: dedup must suppress a second send and
        // leave the stamp untouched.
        $this->run();

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

        $this->run();
        $this->assertSame(1, $this->reminderCount($sub->fan_user_id));

        // Period rolls forward a month: current_period_start now post-dates the
        // first stamp, so the next run must re-arm and send again.
        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));
        $sub->forceFill([
            'current_period_start' => Carbon::parse('2026-07-04 12:00:00'),
            'current_period_end'   => Carbon::parse('2026-08-03 12:00:00'),
        ])->save();

        $this->run();

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

        $this->run();

        foreach ($subs as $label => $sub) {
            $sub = $sub->fresh();
            $this->assertSame(0, $this->reminderCount($sub->fan_user_id), "expected no reminder for {$label} sub");
            $this->assertNull($sub->renewal_reminder_sent_at, "expected null stamp for {$label} sub");
        }
    }
}
