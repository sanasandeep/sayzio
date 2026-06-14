<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\Adapters\StripeAdapter;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Money-path coverage for coin-package crediting.
 *
 * Two layers protect against double-crediting a re-delivered webhook:
 *   1. The webhook router ({@see \App\Http\Controllers\WebhookController})
 *      is idempotent on the (gateway, gateway_ref) PaymentAttempt unique
 *      index — a duplicate delivery never re-runs activation.
 *   2. The activation action ({@see ActivateSubscription::run()}) itself is
 *      re-entrant: it locks the invoice, short-circuits when already paid,
 *      and credits the wallet under the `invoice:<id>` idempotency key.
 *
 * These tests pin BOTH layers so a regression in either one — which would
 * silently mint free coins — fails loudly.
 */
class CoinPackageWebhookCreditTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Coin Buyer '.Str::random(4),
            'email'    => 'coins'.Str::random(6).'@example.test',
            'password' => bcrypt('secret'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    /**
     * Build a pending coin-package invoice mirroring the line-item meta
     * shape that {@see \App\Modules\User\Controllers\WalletController} emits
     * at checkout: meta.kind='coin_package' with coins + bonus + package id.
     */
    private function makeCoinInvoice(User $user, int $coins, int $bonus, int $packageId = 7): Invoice
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
                'label'        => 'Coin pack ('.($coins + $bonus).' coins)',
                'amount_minor' => 1000,
                'quantity'     => 1,
                'meta'         => [
                    'kind'            => 'coin_package',
                    'coin_package_id' => $packageId,
                    'coins'           => $coins,
                    'bonus'           => $bonus,
                ],
            ]],
        ]);
    }

    public function test_coin_package_invoice_credits_base_plus_bonus_exactly_once(): void
    {
        $user    = $this->makeUser();
        $invoice = $this->makeCoinInvoice($user, coins: 500, bonus: 50);

        app(ActivateSubscription::class)->run($invoice, 'stripe', 'evt_first');

        $wallet = app(WalletService::class);
        $this->assertSame(550, $wallet->getBalance($user), 'Base + bonus coins must be credited.');

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $credits = WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get();
        $this->assertCount(1, $credits, 'Exactly one credit transaction should exist.');
        $this->assertSame('invoice:'.$invoice->id, $credits->first()->idempotency_key);
    }

    public function test_redelivered_activation_for_same_invoice_does_not_double_credit(): void
    {
        $user    = $this->makeUser();
        $invoice = $this->makeCoinInvoice($user, coins: 500, bonus: 50);

        $activator = app(ActivateSubscription::class);
        // First delivery credits the wallet.
        $activator->run($invoice, 'stripe', 'evt_first');

        // The second is the gateway re-sending the same event (same
        // invoice). Re-delivery of an already-paid coin invoice must be a
        // clean no-op: the coin block's idempotency short-circuit runs
        // before the "paid but no subscription" fail-safe, so it returns
        // gracefully instead of throwing.
        $activator->run($invoice->fresh(), 'stripe', 'evt_first');

        $this->assertSame(550, app(WalletService::class)->getBalance($user), 'Re-delivery must not double-credit.');
        $this->assertCount(
            1,
            WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get(),
            'Re-delivery must not write a second credit transaction.'
        );
    }

    public function test_wallet_idempotency_key_absorbs_duplicate_credit_even_when_status_guard_bypassed(): void
    {
        // Defense-in-depth: even if the invoice paid-status short-circuit
        // were somehow skipped, the `invoice:<id>` idempotency key on
        // wallet_transactions must still prevent a second credit.
        $user    = $this->makeUser();
        $invoice = $this->makeCoinInvoice($user, coins: 200, bonus: 0);

        $wallet = app(WalletService::class);
        $opts = [
            'reason'          => 'Coin pack purchase (invoice '.$invoice->number.')',
            'invoice_id'      => $invoice->id,
            'idempotency_key' => 'invoice:'.$invoice->id,
        ];

        $first  = $wallet->credit($user, 200, $opts);
        $second = $wallet->credit($user, 200, $opts);

        $this->assertSame($first->id, $second->id, 'Same idempotency key must return the original transaction.');
        $this->assertSame(200, $wallet->getBalance($user), 'Balance must reflect a single credit.');
    }

    public function test_webhook_redelivery_is_idempotent_on_gateway_ref(): void
    {
        $user    = $this->makeUser();
        $invoice = $this->makeCoinInvoice($user, coins: 1000, bonus: 100);

        // Swap in a fake Stripe adapter so the webhook router accepts the
        // signature and parses a canonical payment.succeeded event for our
        // invoice without needing real Stripe credentials.
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
                    'gateway_ref' => 'evt_stripe_123',
                    'raw'         => ['id' => 'evt_stripe_123'],
                ];
            }
        };
        $fake->invoiceId = $invoice->id;
        $this->app->instance(StripeAdapter::class, $fake);

        $this->withoutExceptionHandling();
        $this->postJson('/webhooks/stripe')->assertOk();

        // Re-delivery of the exact same event (same gateway_ref). It must
        // not double-credit and must return cleanly — re-delivery of an
        // already-paid coin invoice is a graceful no-op (the activator's
        // coin idempotency short-circuit runs before the "paid but no
        // subscription" fail-safe).
        $this->postJson('/webhooks/stripe')->assertOk();

        $this->assertSame(1100, app(WalletService::class)->getBalance($user), 'Coins credited once across two deliveries.');
        $this->assertCount(
            1,
            PaymentAttempt::where('gateway', 'stripe')->where('gateway_ref', 'evt_stripe_123')->get(),
            'Only one PaymentAttempt per (gateway, gateway_ref).'
        );
        $this->assertCount(
            1,
            WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get(),
            'Webhook re-delivery must not double-credit.'
        );
    }
}
