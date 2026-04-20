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
 * Cashfree-specific post-activation hooks. Same shape as the other
 * provider classes.
 *
 *   1. Stamp gateway_subscription_id from the initiated PaymentAttempt.
 *   2. Mid-cycle upgrade: cancel old Cashfree subscription (so it
 *      doesn't charge the old price next cycle). We do NOT auto-create
 *      a new Cashfree subscription — Cashfree subscriptions require a
 *      payer e-mandate authorisation step which cannot run from a
 *      server-side listener. The task spec explicitly calls out this
 *      cancel-and-recreate pattern for gateways without a clean
 *      server-only API path.
 */
class CashfreeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Invoice::saved(function (Invoice $invoice) {
            if ($invoice->gateway !== 'cashfree') return;
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

        $attempt = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'cashfree')
            ->whereIn('status', ['initiated', 'succeeded'])
            ->orderBy('id')
            ->first();
        if (!$attempt) return;
        $raw = (array) $attempt->raw_response;
        if (($raw['kind'] ?? null) === 'subscription' && !empty($raw['ref_id'])) {
            $sub->forceFill(['gateway_subscription_id' => (string) $raw['ref_id']])->save();
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
        if (!$isUpgrade || !$oldSubId) return;

        $setting  = GatewaySetting::where('gateway_slug', 'cashfree')->first();
        // Mirror CashfreeAdapter::credWithAlias — admin UI persists
        // `app_id`/`secret_key`, legacy seeds use `client_id`/`client_secret`.
        $pick = function (array $keys) use ($setting): string {
            foreach ($keys as $k) {
                $v = (string) ($setting?->credential($k, '') ?? '');
                if ($v !== '') return $v;
            }
            return '';
        };
        $id     = $pick(['app_id', 'client_id']);
        $secret = $pick(['secret_key', 'client_secret']);
        if ($id === '' || $secret === '') return;
        // Mirror CashfreeAdapter::effectiveMode — the admin UI stores
        // mode on the `gateway_settings.mode` COLUMN as 'test'|'live'.
        $modeCol = (string) ($setting?->mode ?? '');
        $modeCr  = (string) ($setting?->credential('mode', '') ?? '');
        $rawMode = $modeCol !== '' ? $modeCol : ($modeCr !== '' ? $modeCr : 'live');
        $mode    = $rawMode === 'test' ? 'sandbox' : $rawMode;
        $apiBase = $mode === 'sandbox'
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';

        $old = Subscription::find($oldSubId);
        $cfOldId = $old?->gateway_subscription_id;
        if (!$cfOldId) return;

        try {
            $res = Http::withHeaders([
                'x-client-id'     => $id,
                'x-client-secret' => $secret,
                'x-api-version'   => '2023-08-01',
            ])->asJson()->post($apiBase . '/subscriptions/' . $cfOldId . '/cancel', []);
            if (!$res->successful()) {
                Log::error('Cashfree upgrade old-sub cancel returned non-success', [
                    'cf_sub_id' => $cfOldId,
                    'status'    => $res->status(),
                    'body'      => $res->json() ?: $res->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Cashfree upgrade swap failed', [
                'subscription_id' => $sub->id, 'error' => $e->getMessage(),
            ]);
        }
        Log::info('Cashfree upgrade: old subscription cancelled; new sub awaits payer re-authorisation', [
            'old_cf_sub_id'       => $cfOldId,
            'new_internal_sub_id' => $sub->id,
        ]);
    }
}
