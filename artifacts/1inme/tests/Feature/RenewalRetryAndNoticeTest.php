<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\Billing\Adapters\OfflineAdapter;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the two halves of "alert creators the moment their renewal fails,
 * not just when grace is ending" (task 2992):
 *
 *  1. The FIRST renewal-failure notice actually goes out, and it carries a
 *     working "update your payment method" deep-link (the billing page).
 *     Previously nothing proved the creator receives this first notice with
 *     a fix-it path — only the separate one-shot grace-ending warning had
 *     coverage.
 *
 *  2. During the grace window the cron RE-ATTEMPTS chargeRecurring, so a
 *     transient decline (expired card since replaced, momentary outage) can
 *     recover and return the subscription to `active` instead of silently
 *     waiting out the whole window and expiring. The retry is throttled to
 *     roughly once a day so a still-declining card is not hammered hourly.
 */
class RenewalRetryAndNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        CountingThrowingOfflineAdapter::$attempts = 0;
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    protected function makeBuyer(string $country = 'IN'): User
    {
        $u = User::create([
            'name'     => 'Buyer ' . Str::random(4),
            'email'    => 'b' . Str::random(8) . '@e.com',
            'password' => Hash::make('secret'),
            'status'   => 'active',
            'country'  => $country,
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => $country, 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    protected function makePlan(string $label, int $inrMonthlyMinor): Plan
    {
        $plan = Plan::create([
            'name'        => $label,
            'slug'        => Str::slug($label) . '-' . Str::random(4),
            'description' => $label,
            'monthly_price' => $inrMonthlyMinor / 100,
            'annual_price'  => ($inrMonthlyMinor * 10) / 100,
            'trial_days'  => 0,
            'grace_days'  => 7,
            'status'      => 'active',
            'is_default'  => false,
            'is_archived' => false,
            'sort_order'  => 1,
            'features'    => [],
        ]);
        PricingResolver::upsertManyFromMinor($plan, [
            ['INR', 'monthly', $inrMonthlyMinor],
            ['INR', 'annual', $inrMonthlyMinor * 10],
            ['USD', 'monthly', (int) round($inrMonthlyMinor / 80)],
            ['USD', 'annual', (int) round(($inrMonthlyMinor * 10) / 80)],
        ]);
        return $plan;
    }

    protected function makeActiveSub(User $user, Plan $plan, Carbon $start, Carbon $end, array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id'              => $user->id,
            'plan_id'              => $plan->id,
            'status'               => 'active',
            'billing_cycle'        => 'monthly',
            'currency'             => 'INR',
            'gateway'              => 'offline',
            'cancel_at_period_end' => false,
            'current_period_start' => $start,
            'current_period_end'   => $end,
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // 1. First failure notice carries a working "update payment method" link
    // ------------------------------------------------------------------

    public function test_first_renewal_failure_notice_links_to_update_payment_method(): void
    {
        $this->app->bind(OfflineAdapter::class, CountingThrowingOfflineAdapter::class);

        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);

        // Due within the 24h pre-renewal window so renewUpcoming charges it.
        $end = now()->addHours(6);
        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->addHours(6), $end);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        $this->assertSame('past_due', $sub->status, 'failed renewal drops into grace');

        // The creator received the FIRST failure notice...
        $log = EmailLog::where('email_key', 'billing.subscription_renewal_failed')
            ->where('recipient', $user->email)
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'a renewal_failed notice was sent on the first failure');
        $this->assertSame('sent', $log->status);

        // ...and it links them to where they can fix the card on file.
        $this->assertStringContainsString('/user/billing', (string) $log->body,
            'the notice deep-links to the billing page to update payment method');
        $this->assertStringContainsString('update your payment method', (string) $log->body);
    }

    // ------------------------------------------------------------------
    // 2. A recovered charge during grace returns the sub to active
    // ------------------------------------------------------------------

    public function test_grace_retry_recovers_subscription_to_active(): void
    {
        // The card is now good again: the gateway settles the renewal.
        $this->app->bind(OfflineAdapter::class, RecoveringOfflineAdapter::class);

        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);
        $user->forceFill(['plan_id' => $plan->id, 'plan_expires_at' => now()->subHour()])->save();

        // Already in grace: period ended an hour ago, grace open for 6 more days,
        // and we have not retried yet (renewal_retry_at = null).
        $endedAt = now()->subHour();
        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->subHour(), $endedAt, [
            'status'           => 'past_due',
            'grace_until'      => now()->addDays(6),
            'renewal_retry_at' => null,
        ]);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);

        $sub->refresh();
        // THE POINT: the in-grace retry recovered the charge → back to active.
        $this->assertSame('active', $sub->status, 'recovered charge returns the sub to active');
        $this->assertNull($sub->grace_until, 'grace window cleared on recovery');
        $newEnd = Carbon::parse($sub->current_period_end);
        $this->assertTrue($newEnd->isFuture(), 'period advanced into the future on the paid renewal');
        // The lapsed period end was in the past, so the renewal restarts a
        // full cycle from now (no backdating) — roughly one month out.
        $this->assertGreaterThan(now()->addDays(27), $newEnd,
            'renewal restarts a fresh ~1-month cycle from now');
        $this->assertLessThan(now()->addDays(32), $newEnd);

        // The recovery left a single PAID renewal invoice.
        $paid = Invoice::where('subscription_id', $sub->id)->where('status', 'paid')->get();
        $this->assertCount(1, $paid, 'exactly one paid renewal invoice from the recovery');

        // User access mirrors the revived subscription.
        $user->refresh();
        $this->assertSame($plan->id, $user->plan_id);
    }

    // ------------------------------------------------------------------
    // 3. Retries are throttled (~once/day) but DO fire before grace ends
    // ------------------------------------------------------------------

    public function test_grace_retry_is_throttled_then_fires_again_next_interval(): void
    {
        // The card keeps declining; we want to see retry cadence, not recovery.
        $this->app->bind(OfflineAdapter::class, CountingThrowingOfflineAdapter::class);

        $user = $this->makeBuyer();
        $plan = $this->makePlan('Pro', 40000);

        $sub = $this->makeActiveSub($user, $plan, now()->subMonth()->subHour(), now()->subHour(), [
            'status'           => 'past_due',
            'grace_until'      => now()->addDays(6),
            'renewal_retry_at' => null,
        ]);

        // First run: one retry attempt, still declining → stays in grace.
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $this->assertSame(1, CountingThrowingOfflineAdapter::$attempts, 'first grace tick retries once');
        $sub->refresh();
        $this->assertSame('past_due', $sub->status);
        $this->assertNotNull($sub->renewal_retry_at, 'retry attempt was stamped');

        // Second run an hour later: throttled — no new attempt.
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $this->assertSame(1, CountingThrowingOfflineAdapter::$attempts, 'hourly re-run does not re-charge');

        // A day later: the throttle window has elapsed → it retries again,
        // so a multi-day grace window gets several recovery chances.
        Carbon::setTestNow(now()->addHours(25));
        try {
            $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        } finally {
            Carbon::setTestNow();
        }
        $this->assertSame(2, CountingThrowingOfflineAdapter::$attempts, 'retries again after the interval');
        $sub->refresh();
        $this->assertSame('past_due', $sub->status, 'still in grace after a repeated decline');
    }
}

/**
 * Immediately-paying gateway double (recovered card): reuses the real
 * offline invoice-issuing path, then settles it via ActivateSubscription
 * exactly as a synchronous gateway success webhook would — flipping the
 * subscription back to active and extending its period.
 */
class RecoveringOfflineAdapter extends OfflineAdapter
{
    public function chargeRecurring(Subscription $subscription): array
    {
        $result  = parent::chargeRecurring($subscription);
        $invoice = Invoice::findOrFail($result['invoice_id']);
        app(ActivateSubscription::class)->run($invoice, $this->slug());
        return ['kind' => 'paid', 'invoice_id' => $invoice->id];
    }
}

/**
 * Declining gateway double that counts how many times chargeRecurring is
 * invoked, so the throttle test can assert retry cadence. Throws before
 * issuing any invoice, exactly as a synchronous declined charge does.
 */
class CountingThrowingOfflineAdapter extends OfflineAdapter
{
    public static int $attempts = 0;

    public function chargeRecurring(Subscription $subscription): array
    {
        self::$attempts++;
        throw new \RuntimeException('gateway declined the recurring charge');
    }
}
