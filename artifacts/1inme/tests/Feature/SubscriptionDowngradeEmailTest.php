<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\EmailLog;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\SubscriptionAddon;
use App\Modules\User\Models\User;
use App\Services\Billing\SubscriptionLifecycle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the downgrade billing emails (Task #2970): the
 * downgrade-scheduled notice fired by scheduleDowngrade(), the
 * downgrade-applied notice fired by applyScheduledDowngrade()
 * (which must list dropped add-ons), and the guarantee that running
 * the renewal cron twice never sends a second applied email.
 *
 * Emails are asserted via the email_logs table, NOT Mail::fake()
 * counters: Emailer routes downgrade notices through Mail::raw()
 * (text format), and MailFake's raw() is a no-op, so the only
 * reliable record is the email_logs row Emailer writes on every send.
 * See .agents/memory/emailer-swallows-transport-errors.md and
 * .agents/memory/mailfake-raw-noop.md.
 */
class SubscriptionDowngradeEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
        // Downgrade notices go out as text/raw; stop any real transport.
        Mail::fake();
    }

    protected function makeUser(): User
    {
        $u = User::create([
            'name' => 'B'.Str::random(4), 'email' => 'b'.Str::random(6).'@e.com',
            'password' => bcrypt('x'), 'country' => 'IN', 'status' => 'active',
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => 'IN', 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 R', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    protected function makePlan(string $slug, float $monthly, int $grace = 7): Plan
    {
        return Plan::create([
            'name' => ucfirst($slug), 'slug' => $slug.'-'.Str::random(4),
            'monthly_price' => $monthly, 'annual_price' => $monthly * 10,
            'trial_days' => 0, 'grace_days' => $grace, 'refund_window_days' => 7,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
    }

    protected function payPlanInvoice(User $user, Plan $plan, string $cycle = 'monthly'): Subscription
    {
        $items = [[
            'label' => $plan->name.' ('.$cycle.')',
            'amount_minor' => (int) round($plan->monthly_price * 100),
            'quantity' => 1,
            'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => $cycle],
        ]];
        $invoice = ActivateSubscription::issuePendingInvoice($user, $items, 'INR');
        return (new ActivateSubscription())->run($invoice, 'offline', 'ref-'.$invoice->number);
    }

    /** Count of "sent" downgrade emails of a given key for a recipient. */
    protected function sentCount(string $key, string $email): int
    {
        return EmailLog::where('email_key', $key)
            ->where('recipient', $email)
            ->where('status', 'sent')
            ->count();
    }

    public function test_schedule_downgrade_sends_scheduled_email_with_target_plan_and_effective_date(): void
    {
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $low  = $this->makePlan('starter', 9.00);
        $sub  = $this->payPlanInvoice($user, $high);

        app(SubscriptionLifecycle::class)->scheduleDowngrade($sub->fresh(), $low);

        $log = EmailLog::where('email_key', 'billing.subscription_downgrade_scheduled')
            ->where('recipient', $user->email)
            ->latest('id')->first();

        $this->assertNotNull($log, 'a downgrade-scheduled email was logged');
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString($low->name, (string) $log->body, 'body names the target plan');
        $this->assertStringContainsString($low->name, (string) $log->subject, 'subject names the target plan');

        $effective = Carbon::parse($sub->fresh()->current_period_end)->toDateString();
        $this->assertStringContainsString($effective, (string) $log->body, 'body states the effective date');
    }

    public function test_cancel_scheduled_downgrade_clears_target_and_sends_cancelled_email(): void
    {
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $low  = $this->makePlan('starter', 9.00);
        $sub  = $this->payPlanInvoice($user, $high);

        // A downgrade is pending, then the user cancels it.
        $sub->forceFill(['scheduled_downgrade_plan_id' => $low->id])->save();
        app(SubscriptionLifecycle::class)->cancelScheduledDowngrade($sub->fresh());

        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id, 'scheduled target cleared');
        $this->assertSame($high->id, $sub->fresh()->plan_id, 'user stays on the current plan');

        $log = EmailLog::where('email_key', 'billing.subscription_downgrade_cancelled')
            ->where('recipient', $user->email)
            ->latest('id')->first();

        $this->assertNotNull($log, 'a downgrade-cancelled email was logged');
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString($low->name, (string) $log->subject, 'subject names the cancelled target plan');
        $this->assertStringContainsString($low->name, (string) $log->body, 'body names the cancelled target plan');
        $this->assertStringContainsString($high->name, (string) $log->body, 'body confirms the current plan stays');
    }

    public function test_cancel_with_no_pending_downgrade_sends_no_email(): void
    {
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $sub  = $this->payPlanInvoice($user, $high);

        // No downgrade is scheduled — cancelling must be a no-op and must
        // NOT send a misleading "your downgrade was cancelled" email.
        app(SubscriptionLifecycle::class)->cancelScheduledDowngrade($sub->fresh());

        $this->assertSame(
            0,
            $this->sentCount('billing.subscription_downgrade_cancelled', $user->email),
            'no cancellation email when there was nothing scheduled',
        );
    }

    public function test_apply_scheduled_downgrade_sends_applied_email_listing_dropped_addons(): void
    {
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $low  = $this->makePlan('starter', 9.00);

        // An add-on the HIGH plan carries but the LOW plan does not — it
        // must be dropped on apply and named in the applied email.
        $addon = Addon::create([
            'name' => 'Priority Support', 'slug' => 'prio-'.Str::random(4),
            'type' => 'recurring', 'monthly_price' => 5.00, 'annual_price' => 50.00,
            'status' => 'active', 'sort_order' => 1,
        ]);
        $high->addons()->attach($addon->id);

        $sub = $this->payPlanInvoice($user, $high);
        SubscriptionAddon::create([
            'subscription_id' => $sub->id, 'addon_id' => $addon->id, 'qty' => 1,
        ]);
        $sub->forceFill(['scheduled_downgrade_plan_id' => $low->id])->save();

        $target = app(SubscriptionLifecycle::class)->applyScheduledDowngrade($sub->fresh());

        $this->assertNotNull($target, 'downgrade applied to a valid lower plan');
        $this->assertSame($low->id, $target->id);
        $this->assertSame($low->id, $sub->fresh()->plan_id);
        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id, 'schedule cleared after apply');
        $this->assertSame(0, SubscriptionAddon::where('subscription_id', $sub->id)->count(), 'ineligible add-on dropped');

        $log = EmailLog::where('email_key', 'billing.subscription_downgrade_applied')
            ->where('recipient', $user->email)
            ->latest('id')->first();

        $this->assertNotNull($log, 'a downgrade-applied email was logged');
        $this->assertSame('sent', $log->status);
        $this->assertStringContainsString($low->name, (string) $log->body, 'body names the new plan');
        $this->assertStringContainsString($addon->name, (string) $log->body, 'body lists the dropped add-on');
    }

    /**
     * Cancel-vs-apply race, apply wins: the renewal cron applies the
     * downgrade a beat before the user's "Cancel downgrade" click lands on a
     * stale model that still believes a downgrade is pending. The cancel must
     * detect (under the row lock) that the schedule is already gone, report
     * that it could NOT cancel, leave the applied downgrade in place, and send
     * no misleading "your downgrade was cancelled" email.
     */
    public function test_cancel_after_downgrade_already_applied_is_not_misleading(): void
    {
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $low  = $this->makePlan('starter', 9.00);
        $sub  = $this->payPlanInvoice($user, $high);
        $sub->forceFill(['scheduled_downgrade_plan_id' => $low->id])->save();

        // The user opened the billing page while the downgrade was still
        // pending: this stale snapshot still carries the schedule.
        $stale = Subscription::find($sub->id);

        // The renewal cron applies the downgrade first.
        $applied = app(SubscriptionLifecycle::class)->applyScheduledDowngrade($sub->fresh());
        $this->assertNotNull($applied, 'cron applied the downgrade');
        $this->assertSame($low->id, $sub->fresh()->plan_id);

        // Now the "Cancel downgrade" click lands on the stale model.
        $cancelled = app(SubscriptionLifecycle::class)->cancelScheduledDowngrade($stale);

        $this->assertFalse($cancelled, 'cancel reports it could not cancel (already applied)');
        $this->assertSame($low->id, $sub->fresh()->plan_id, 'the downgrade stays applied');
        $this->assertSame(
            0,
            $this->sentCount('billing.subscription_downgrade_cancelled', $user->email),
            'no cancellation email after the downgrade already took effect',
        );
    }

    /**
     * Cancel-vs-apply race, cancel wins: the user cancels first, then the
     * renewal cron's apply lands on a stale snapshot that still points at the
     * (now-cancelled) schedule. The apply must no-op under the row lock —
     * leaving the user on their current plan with no applied email.
     */
    public function test_apply_after_cancel_committed_is_a_noop(): void
    {
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $low  = $this->makePlan('starter', 9.00);
        $sub  = $this->payPlanInvoice($user, $high);
        $sub->forceFill(['scheduled_downgrade_plan_id' => $low->id])->save();

        // The cron loaded the sub with the schedule still pending.
        $stale = Subscription::find($sub->id);

        // The user cancels first, and it actually cancels.
        $cancelled = app(SubscriptionLifecycle::class)->cancelScheduledDowngrade($sub->fresh());
        $this->assertTrue($cancelled, 'the cancel succeeds');
        $this->assertNull($sub->fresh()->scheduled_downgrade_plan_id);

        // The cron's apply now lands on its stale snapshot.
        $target = app(SubscriptionLifecycle::class)->applyScheduledDowngrade($stale);

        $this->assertNull($target, 'apply is a no-op once the schedule was cancelled');
        $this->assertSame($high->id, $sub->fresh()->plan_id, 'the user stays on the current plan');
        $this->assertSame(
            0,
            $this->sentCount('billing.subscription_downgrade_applied', $user->email),
            'no applied email after the downgrade was cancelled',
        );
    }

    public function test_renewal_cron_run_twice_sends_only_one_applied_email(): void
    {
        GatewaySetting::where('gateway_slug', 'offline')->update(['is_enabled' => true]);
        $user = $this->makeUser();
        $high = $this->makePlan('pro', 20.00);
        $low  = $this->makePlan('starter', 9.00);
        $sub  = $this->payPlanInvoice($user, $high);

        // Period has ended and a downgrade is pending → cron applies it.
        $sub->forceFill([
            'gateway'                     => 'offline',
            'current_period_start'        => now()->subDays(30),
            'current_period_end'          => now()->subMinute(),
            'scheduled_downgrade_plan_id' => $low->id,
        ])->save();

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $sub->refresh();
        $this->assertSame($low->id, $sub->plan_id, 'downgrade applied on first run');
        $this->assertNull($sub->scheduled_downgrade_plan_id, 'schedule cleared after first apply');
        $this->assertSame(1, $this->sentCount('billing.subscription_downgrade_applied', $user->email));

        // Second run within the same period: the schedule is gone and a
        // renewal invoice already exists, so no second apply / email.
        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
        $this->assertSame(
            1,
            $this->sentCount('billing.subscription_downgrade_applied', $user->email),
            'renewal retry does not trigger a second applied email',
        );
    }
}
