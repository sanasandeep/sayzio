<?php

namespace App\Providers;

use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * PayPal-specific post-activation hooks. Mirrors Razorpay/Stripe
 * providers: adapts the locked activation pipeline for gateway-side
 * effects without touching it.
 *
 *   1. First-cycle subscription activation: stamp the internal
 *      Subscription's gateway_subscription_id from the initiated
 *      PaymentAttempt row (raw_response.kind=subscription, ref_id=...).
 *
 *   2. Mid-cycle upgrade: the one-time Order covered the prorated
 *      charge. Cancel the OLD PayPal subscription so it doesn't keep
 *      billing at the old price. Creating a new PayPal subscription
 *      requires payer approval (there is no server-only "create-from-
 *      customer-id" path like Stripe), so we do NOT auto-create a new
 *      sub here — the next renewal falls to our cron's pending_gateway
 *      semantics and operators can prompt the user to re-authorize
 *      via a follow-up checkout. Logged loudly for ops visibility.
 */
class PaypalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Invoice::saved(function (Invoice $invoice) {
            if ($invoice->gateway !== 'paypal') return;
            if ($invoice->status !== 'paid') return;
            if (!$invoice->subscription_id) return;
            if (!$invoice->wasChanged('status')) return;

            $sub = Subscription::find($invoice->subscription_id);
            if (!$sub) return;

            $this->stampGatewaySubscriptionId($invoice, $sub);
            $this->handleUpgradeGatewaySwap($invoice, $sub);
        });
    }

    protected function stampGatewaySubscriptionId(Invoice $invoice, Subscription $sub): void
    {
        if ($sub->gateway_subscription_id) return;

        $attempts = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'paypal')
            ->whereIn('status', ['succeeded', 'initiated'])
            ->orderByDesc('id')
            ->get();
        foreach ($attempts as $attempt) {
            $raw  = (array) $attempt->raw_response;
            $kind = $raw['kind'] ?? null;
            $ref  = $raw['ref_id'] ?? null;
            // The initiated row of a subscription checkout stashes
            // ref_id=I-SUBID. The webhook-activated row stashes
            // paypal_subscription_id directly.
            $ppSub = $kind === 'subscription' && is_string($ref) && $ref !== '' ? $ref
                   : ($raw['paypal_subscription_id'] ?? null);
            if (is_string($ppSub) && $ppSub !== '') {
                $sub->forceFill(['gateway_subscription_id' => $ppSub])->save();
                return;
            }
        }
    }

    protected function handleUpgradeGatewaySwap(Invoice $invoice, Subscription $sub): void
    {
        $items = is_array($invoice->line_items) ? $invoice->line_items : [];
        $isUpgrade = false; $oldSubId = null;
        foreach ($items as $li) {
            $meta = $li['meta'] ?? [];
            if (($meta['kind'] ?? null) === 'plan_upgrade') {
                $isUpgrade = true;
                $oldSubId  = (int) ($meta['upgrade_from_subscription_id'] ?? 0);
                break;
            }
        }
        if (!$isUpgrade) return;

        $setting  = GatewaySetting::where('gateway_slug', 'paypal')->first();
        $clientId = (string) ($setting?->credential('client_id', '') ?? '');
        $secret   = (string) ($setting?->credential('client_secret', '') ?? '');
        if ($clientId === '' || $secret === '') return;
        // Mirror PaypalAdapter::effectiveMode(): the admin UI persists
        // mode on the `gateway_settings.mode` COLUMN as 'test'|'live'
        // (outside the encrypted blob). Legacy seeds may still stash
        // `mode` inside credentials. 'test' maps to PayPal's sandbox.
        $modeCol = (string) ($setting?->mode ?? '');
        $modeCr  = (string) ($setting?->credential('mode', '') ?? '');
        $rawMode = $modeCol !== '' ? $modeCol : ($modeCr !== '' ? $modeCr : 'live');
        $mode    = $rawMode === 'test' ? 'sandbox' : $rawMode;
        $apiBase = $mode === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        if (!$oldSubId) return;
        $old = Subscription::find($oldSubId);
        $ppOldId = $old?->gateway_subscription_id;
        if (!$ppOldId) return;

        try {
            // Fetch an OAuth token for this one call.
            $tokenRes = Http::withBasicAuth($clientId, $secret)
                ->asForm()
                ->post($apiBase . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            if (!$tokenRes->successful()) {
                Log::error('PayPal upgrade: token fetch failed', ['body' => $tokenRes->body()]);
                return;
            }
            $token = (string) $tokenRes->json('access_token');

            // Cancel the old PayPal subscription so it stops charging.
            $cancelRes = Http::withToken($token)
                ->asJson()
                ->post($apiBase . '/v1/billing/subscriptions/' . $ppOldId . '/cancel', [
                    'reason' => 'Upgraded to a different plan',
                ]);
            if (!$cancelRes->successful() && $cancelRes->status() !== 204) {
                Log::error('PayPal upgrade old-sub cancel returned non-success', [
                    'paypal_sub_id' => $ppOldId,
                    'status'        => $cancelRes->status(),
                    'body'          => $cancelRes->json() ?: $cancelRes->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('PayPal upgrade swap failed', [
                'subscription_id' => $sub->id, 'error' => $e->getMessage(),
            ]);
        }

        // We deliberately do NOT auto-create a new PayPal subscription
        // here. PayPal billing subscriptions require payer approval
        // (redirect-to-approve flow), which can't be done from a
        // server-side Invoice::saved listener. Operators should
        // prompt the user to re-authorize on next renewal.
        Log::info('PayPal upgrade: old subscription cancelled; new sub awaits payer re-authorisation', [
            'old_paypal_sub_id'     => $ppOldId,
            'new_internal_sub_id'   => $sub->id,
        ]);
    }
}
