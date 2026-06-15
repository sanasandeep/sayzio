<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\Billing\Adapters\StripeAdapter;
use App\Services\Billing\Contracts\GatewayAdapter;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\SubscriptionLifecycle;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Coverage for the three best-effort "page the team" critical-event alert
 * sites. Each is a high-signal ops hook that must (a) fire when it should,
 * (b) stay quiet when it shouldn't, and (c) never let a dead alert webhook
 * break the request / worker / cron that triggered it.
 *
 *   1. {@see \App\Http\Controllers\WebhookController} — a confirmed charge
 *      whose activation throws must alert (critical) AND still re-throw so
 *      the gateway sees a 5xx and retries.
 *   2. {@see \App\Console\Commands\RenewDueSubscriptions} — recurring-charge
 *      failures only page the team once they cross a threshold (gateway
 *      outage), not on isolated declines below it.
 *   3. The global {@see \Illuminate\Support\Facades\Queue::failing()} hook in
 *      {@see \App\Providers\AppServiceProvider} — a terminal job failure
 *      alerts exactly once and never throws into the worker.
 *
 * All three resolve NotificationService via the container, so we bind a
 * Mockery double to assert the call shape without hitting a real webhook.
 */
class CriticalEventAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Alert User '.Str::random(4),
            'email'    => 'alert'.Str::random(6).'@example.test',
            'password' => bcrypt('secret'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    private function makeInvoice(User $user): Invoice
    {
        return Invoice::create([
            'number'                   => 'INV/TEST/'.Str::upper(Str::random(8)),
            'financial_year'           => '2026-27',
            'seq'                      => random_int(1, 1_000_000),
            'user_id'                  => $user->id,
            'currency'                 => 'USD',
            'subtotal_minor'           => 1000,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => 1000,
            'billing_address_snapshot' => [],
            'merchant_snapshot'        => [],
            'tax_breakdown'            => [],
            'status'                   => 'pending',
            'line_items'               => [[
                'label'        => 'Pro plan',
                'amount_minor' => 1000,
                'quantity'     => 1,
                'meta'         => ['kind' => 'plan'],
            ]],
        ]);
    }

    /**
     * Swap in a fake Stripe adapter so the webhook router accepts the
     * signature and parses a canonical payment.succeeded event for $invoice
     * without needing real Stripe credentials.
     */
    private function fakeStripeSucceededFor(Invoice $invoice): void
    {
        $fake = new class extends StripeAdapter {
            public ?int $invoiceId = null;
            public function verifyWebhook(Request $request): bool
            {
                return true;
            }
            public function parseEvent(Request $request): array
            {
                return [
                    'type'        => 'payment.succeeded',
                    'invoice_id'  => $this->invoiceId,
                    'gateway_ref' => 'evt_stripe_alert',
                    'raw'         => ['id' => 'evt_stripe_alert'],
                ];
            }
        };
        $fake->invoiceId = $invoice->id;
        $this->app->instance(StripeAdapter::class, $fake);
    }

    public function test_webhook_alerts_critical_and_rethrows_when_activation_fails(): void
    {
        $user    = $this->makeUser();
        $invoice = $this->makeInvoice($user);
        $this->fakeStripeSucceededFor($invoice);

        // The gateway confirmed the money moved, but turning it into a plan
        // throws — the customer may have paid without receiving anything.
        $activator = Mockery::mock(ActivateSubscription::class);
        $activator->shouldReceive('run')
            ->once()
            ->andThrow(new \RuntimeException('plan grant blew up'));
        $this->app->instance(ActivateSubscription::class, $activator);

        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('systemAlert')
            ->once()
            ->with(
                'Payment activation failed after a successful charge',
                Mockery::type('string'),
                'critical',
                Mockery::on(fn ($ctx) => is_array($ctx)
                    && ($ctx['gateway'] ?? null) === 'stripe'
                    && (int) ($ctx['invoice_id'] ?? 0) === $invoice->id
                    && ($ctx['gateway_ref'] ?? null) === 'evt_stripe_alert'),
            )
            ->andReturn(['enabled' => false, 'channels' => []]);
        $this->app->instance(NotificationService::class, $notifier);

        // The router catches, alerts, then re-throws → gateway sees a 5xx and
        // retries (Laravel renders the re-thrown exception as a 500).
        $this->postJson('/webhooks/stripe')->assertStatus(500);
    }

    /**
     * Bind a GatewayManager whose adapter always fails chargeRecurring with a
     * generic error (gateway-outage shape, NOT NotImplementedException) plus a
     * no-op SubscriptionLifecycle so the command's failure counter climbs
     * without real side effects.
     */
    private function bindFailingGateway(): void
    {
        $adapter = Mockery::mock(GatewayAdapter::class);
        $adapter->shouldReceive('chargeRecurring')
            ->andThrow(new \RuntimeException('gateway is down'));

        $gateways = Mockery::mock(GatewayManager::class);
        $gateways->shouldReceive('for')->andReturn($adapter);
        $this->app->instance(GatewayManager::class, $gateways);

        $lifecycle = Mockery::mock(SubscriptionLifecycle::class);
        $lifecycle->shouldReceive('markRenewalFailed');
        $lifecycle->shouldIgnoreMissing();
        $this->app->instance(SubscriptionLifecycle::class, $lifecycle);
    }

    /** Create $count subscriptions all due for renewal within the next 24h. */
    private function makeDueSubscriptions(int $count): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'description' => 'Pro',
            'monthly_price' => 9.99, 'annual_price' => 99, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);

        for ($i = 0; $i < $count; $i++) {
            Subscription::create([
                'user_id'              => $this->makeUser()->id,
                'plan_id'              => $plan->id,
                'status'               => 'active',
                'billing_cycle'        => 'monthly',
                'current_period_start' => now()->subDays(29),
                'current_period_end'   => now()->addHours(12),
                'cancel_at_period_end' => false,
                'gateway'              => 'stripe',
                'currency'             => 'USD',
            ]);
        }
    }

    public function test_renewal_failure_spike_stays_quiet_below_threshold(): void
    {
        $this->bindFailingGateway();
        // One under the threshold (5) — routine declines, not an outage.
        $this->makeDueSubscriptions(4);

        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('systemAlert')->never();
        $this->app->instance(NotificationService::class, $notifier);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
    }

    public function test_renewal_failure_spike_alerts_once_at_threshold(): void
    {
        $this->bindFailingGateway();
        // Exactly at the threshold (5) — looks like a gateway/credential break.
        $this->makeDueSubscriptions(5);

        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('systemAlert')
            ->once()
            ->with(
                'Subscription renewal charges are failing',
                Mockery::type('string'),
                'error',
                Mockery::on(fn ($ctx) => is_array($ctx) && (int) ($ctx['failures'] ?? 0) === 5),
            )
            ->andReturn(['enabled' => false, 'channels' => []]);
        $this->app->instance(NotificationService::class, $notifier);

        $this->artisan('subscriptions:renew-due')->assertExitCode(0);
    }

    private function fakeFailedJob(): Job
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\SomeJob');
        $job->shouldReceive('getQueue')->andReturn('default');
        return $job;
    }

    public function test_failed_background_job_alert_fires_exactly_once(): void
    {
        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('systemAlert')
            ->once()
            ->with(
                'Background job failed',
                Mockery::type('string'),
                'error',
                Mockery::on(fn ($ctx) => is_array($ctx) && ($ctx['job'] ?? null) === 'App\\Jobs\\SomeJob'),
            )
            ->andReturn(['enabled' => false, 'channels' => []]);
        $this->app->instance(NotificationService::class, $notifier);

        // The Queue::failing hook is registered as a JobFailed event listener
        // in AppServiceProvider::boot, so dispatching the event exercises it.
        event(new JobFailed('redis', $this->fakeFailedJob(), new \RuntimeException('boom')));
    }

    public function test_failed_background_job_alert_never_throws_into_worker(): void
    {
        // A dead alert webhook must never bubble out of the worker — the boot
        // listener wraps systemAlert in try/catch.
        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('systemAlert')
            ->once()
            ->andThrow(new \RuntimeException('alert webhook is dead'));
        $this->app->instance(NotificationService::class, $notifier);

        event(new JobFailed('redis', $this->fakeFailedJob(), new \RuntimeException('boom')));

        $this->assertTrue(true, 'Dispatching JobFailed must not throw even when the alert site fails.');
    }
}
