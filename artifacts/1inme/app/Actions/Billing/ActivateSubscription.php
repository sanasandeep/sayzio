<?php

namespace App\Actions\Billing;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\SubscriptionAddon;
use App\Modules\User\Models\SubscriptionCreditReview;
use App\Services\Billing\WalletService;
use App\Services\InvoiceService;
use App\Services\TaxCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Gateway-agnostic activation pipeline. Given a paid invoice:
 *   1. Create (or extend) a Subscription row for (user, plan).
 *   2. Attach addons + qty from invoice line_items meta.
 *   3. Flip users.plan_id / billing_cycle / plan_expires_at so plan
 *      gating kicks in immediately.
 *   4. Mark the invoice paid + stamp paid_at + gateway.
 *   5. Render the tax-invoice PDF and email a receipt.
 *
 * Re-entrancy: the whole thing runs inside a DB transaction with a
 * row-lock on the invoice. If the invoice is already `paid` with a
 * linked subscription we return immediately — safe to call twice for
 * the same webhook delivery or on admin double-click.
 */
class ActivateSubscription
{
    public function run(Invoice $invoice, string $gateway, ?string $gatewayRef = null): Subscription
    {
        return DB::transaction(function () use ($invoice, $gateway, $gatewayRef) {
            /** @var Invoice $fresh */
            $fresh = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status === 'paid' && $fresh->subscription_id) {
                return Subscription::findOrFail($fresh->subscription_id);
            }

            $items   = is_array($fresh->line_items) ? $fresh->line_items : [];

            // Coin-package invoices don't create a Subscription. Credit
            // the user's wallet using the invoice id as the idempotency
            // key (so a re-delivered webhook is a no-op), mark the
            // invoice paid, and email a receipt. We return the user's
            // most recent active subscription if any (callers that only
            // need a return value won't blow up); webhook routing
            // doesn't actually use the return value.
            //
            // This block (including its already-paid short-circuit) runs
            // BEFORE the "paid but no subscription" fail-safe below,
            // because coin invoices intentionally never get a
            // subscription_id — otherwise a re-delivered coin webhook
            // would trip the fail-safe and throw instead of cleanly
            // no-op'ing.
            $coinItems = array_filter($items, fn($i) => (($i['meta']['kind'] ?? null) === 'coin_package'));
            if (!empty($coinItems)) {
                if ($fresh->status === 'paid') {
                    return Subscription::where('user_id', $fresh->user_id)->latest('id')->first()
                        ?? new Subscription(['user_id' => $fresh->user_id]);
                }
                $totalCoins = 0;
                $packageId  = null;
                foreach ($coinItems as $ci) {
                    $m = $ci['meta'] ?? [];
                    $totalCoins += (int) ($m['coins'] ?? 0) + (int) ($m['bonus'] ?? 0);
                    $packageId = $packageId ?? (int) ($m['coin_package_id'] ?? 0) ?: null;
                }
                if ($totalCoins > 0) {
                    app(WalletService::class)->credit($fresh->user, $totalCoins, [
                        'reason'          => 'Coin pack purchase (invoice ' . $fresh->number . ')',
                        'invoice_id'      => $fresh->id,
                        'coin_package_id' => $packageId,
                        'idempotency_key' => 'invoice:' . $fresh->id,
                    ]);
                }
                $fresh->forceFill([
                    'gateway' => $gateway,
                    'status'  => 'paid',
                    'paid_at' => now(),
                ])->save();
                $this->sendReceipt($fresh);
                return Subscription::where('user_id', $fresh->user_id)->latest('id')->first()
                    ?? new Subscription(['user_id' => $fresh->user_id]);
            }

            // Fail-safe: a plan/subscription invoice is already marked paid
            // but not linked to a subscription. Don't silently create a new
            // one — that's a data inconsistency that needs a human to look
            // at. (Coin invoices are handled above and never reach here.)
            if ($fresh->status === 'paid' && !$fresh->subscription_id) {
                Log::error('Invoice already paid but missing subscription_id', [
                    'invoice_id' => $fresh->id, 'invoice_number' => $fresh->number,
                ]);
                throw new \RuntimeException("Invoice {$fresh->number} is already marked paid but has no subscription; please investigate.");
            }

            $planId  = null;
            $cycle   = 'monthly';
            $addons  = [];
            $intent  = 'plan'; // plan | plan_renewal | plan_upgrade
            $renewSubId   = null;
            $upgradeFromSubId = null;
            foreach ($items as $item) {
                $meta = $item['meta'] ?? [];
                $kind = $meta['kind'] ?? null;
                if (in_array($kind, ['plan', 'plan_renewal', 'plan_upgrade'], true)) {
                    $planId = (int) ($meta['plan_id'] ?? $planId);
                    $cycle  = (string) ($meta['cycle'] ?? $cycle);
                    $intent = $kind;
                    $renewSubId       = (int) ($meta['renew_subscription_id'] ?? 0) ?: $renewSubId;
                    $upgradeFromSubId = (int) ($meta['upgrade_from_subscription_id'] ?? 0) ?: $upgradeFromSubId;
                } elseif ($kind === 'addon') {
                    $addons[(int) $meta['addon_id']] = (int) ($meta['qty'] ?? 1);
                }
            }
            if (!$planId) {
                throw new \RuntimeException("Invoice {$fresh->number} has no plan line item.");
            }
            $plan = Plan::findOrFail($planId);
            $user = $fresh->user;

            $months = $cycle === 'annual' ? 12 : 1;
            $now    = now();

            // Renewal: extend the existing subscription's period_end instead
            // of creating a new row.
            if ($intent === 'plan_renewal' && $renewSubId && ($existing = Subscription::find($renewSubId))) {
                $base = \Carbon\Carbon::parse($existing->current_period_end);
                if ($base->isPast()) $base = $now->copy();
                $existing->forceFill([
                    'status'               => 'active',
                    'current_period_start' => $existing->current_period_end,
                    'current_period_end'   => $base->copy()->addMonths($months),
                    'grace_until'          => null,
                    'gateway'              => $gateway,
                ])->save();
                $subscription = $existing;
            }
            // Upgrade: full price for a FRESH full cycle from now (no
            // proration). The leftover days + add-on time on the old plan
            // are NOT auto-credited — instead we flag a credit review for
            // an admin (see captureCreditReview below). Mark the old row
            // cancelled + replaced_by so the timeline walks cleanly.
            elseif ($intent === 'plan_upgrade' && $upgradeFromSubId && ($old = Subscription::find($upgradeFromSubId))) {
                $subscription = Subscription::create([
                    'user_id'              => $user->id,
                    'plan_id'              => $plan->id,
                    'status'               => 'active',
                    'billing_cycle'        => $cycle,
                    'current_period_start' => $now,
                    'current_period_end'   => $now->copy()->addMonths($months),
                    'gateway'              => $gateway,
                    'currency'             => $fresh->currency,
                ]);
                // Carry active add-ons forward to the new subscription —
                // the upgrade replaces the plan row, not the add-on
                // portfolio. Add-ons purchased on the upgrade invoice
                // itself are added by the $addons loop below (keyed by
                // addon_id, so duplicates would collide — existing addons
                // carried forward are merged with invoice addons, invoice
                // qty wins on conflict).
                $carried = [];
                foreach ($old->addons()->get() as $sa) {
                    $carried[(int) $sa->addon_id] = (int) $sa->qty;
                }
                foreach ($carried as $addonId => $qty) {
                    if (!array_key_exists($addonId, $addons)) {
                        SubscriptionAddon::create([
                            'subscription_id' => $subscription->id,
                            'addon_id'        => $addonId,
                            'qty'             => max(1, $qty),
                        ]);
                    }
                }
                $old->forceFill([
                    'status'         => 'cancelled',
                    'replaced_by_id' => $subscription->id,
                    'cancel_at'      => $now,
                ])->save();

                // Flag the forfeited leftover (old-plan days + carried
                // add-on time) for optional admin credit. NOT auto-applied.
                $this->captureCreditReview($old, $subscription, $now);
            } else {
                $end = $now->copy()->addMonths($months);
                $subscription = Subscription::create([
                    'user_id'              => $user->id,
                    'plan_id'              => $plan->id,
                    'status'               => 'active',
                    'billing_cycle'        => $cycle,
                    'current_period_start' => $now,
                    'current_period_end'   => $end,
                    'gateway'              => $gateway,
                    'currency'             => $fresh->currency,
                ]);
            }

            foreach ($addons as $addonId => $qty) {
                SubscriptionAddon::create([
                    'subscription_id' => $subscription->id,
                    'addon_id'        => $addonId,
                    'qty'             => max(1, $qty),
                ]);
            }

            $user->forceFill([
                'plan_id'         => $plan->id,
                'billing_cycle'   => $cycle,
                'plan_expires_at' => $subscription->current_period_end,
            ])->save();

            $fresh->forceFill([
                'subscription_id' => $subscription->id,
                'gateway'         => $gateway,
                'status'          => 'paid',
                'paid_at'         => $now,
            ])->save();

            $this->grantPlanCoins($plan, $subscription, $user, $cycle);

            $this->sendReceipt($fresh);

            // If this invoice paid a custom-plan offer, mark the request as paid.
            $this->resolveCustomPlanRequest($fresh, $plan);

            return $subscription;
        });
    }

    /**
     * Credit the plan's included coin grant for the current billing period,
     * keyed with an idempotency key derived from `subscription_id + period_start`
     * so webhook re-deliveries and manual retries are always no-ops for an
     * already-granted period.
     *
     * Monthly subscribers receive `features.included_coins_monthly` coins;
     * annual subscribers receive `features.included_coins_yearly` coins.
     * A zero or absent value skips the grant entirely.
     *
     * Best-effort: failures are logged but never abort the calling transaction.
     */
    protected function grantPlanCoins(Plan $plan, Subscription $subscription, $user, string $cycle): void
    {
        try {
            $features = is_array($plan->features) ? $plan->features : [];
            $amount = $cycle === 'annual'
                ? (int) ($features['included_coins_yearly'] ?? 0)
                : (int) ($features['included_coins_monthly'] ?? 0);

            if ($amount <= 0) {
                return;
            }

            $periodStart = \Carbon\Carbon::parse($subscription->current_period_start)->format('Y-m-d');
            $idempotencyKey = "plan_grant:sub:{$subscription->id}:from:{$periodStart}";

            app(WalletService::class)->credit($user, $amount, [
                'reason'          => 'Included with your ' . $plan->name . ' plan',
                'idempotency_key' => $idempotencyKey,
                'meta'            => [
                    'kind'            => 'plan_coin_grant',
                    'plan_id'         => $plan->id,
                    'subscription_id' => $subscription->id,
                    'cycle'           => $cycle,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Plan coin grant failed: ' . $e->getMessage(), [
                'plan_id'         => $plan->id ?? null,
                'subscription_id' => $subscription->id ?? null,
                'cycle'           => $cycle,
            ]);
        }
    }

    /**
     * Mark any pending custom plan request as paid once the provisioned plan
     * invoice settles. Best-effort — never throws.
     */
    private function resolveCustomPlanRequest(Invoice $invoice, Plan $plan): void
    {
        try {
            \App\Modules\Admin\Models\CustomPlanRequest::where('provisioned_plan_id', $plan->id)
                ->where('status', 'approved')
                ->update([
                    'status'     => 'paid',
                    'invoice_id' => $invoice->id,
                    'handled_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Custom plan request resolution failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a pending invoice for a user's checkout cart. Tax is
     * calculated at this moment and frozen on the invoice snapshot.
     * Status starts as `pending`; the gateway handoff moves it forward.
     *
     * @param array<int,array{label:string,amount_minor:int,quantity?:int,meta?:array}> $items
     */
    public static function issuePendingInvoice($user, array $items, string $currency): Invoice
    {
        $billing = BillingAddress::where('user_id', $user->id)->first();
        $address = [
            'country'       => $billing?->country ?? ($user->country ?? null),
            'region'        => $billing?->region,
            'postal_code'   => $billing?->postal_code,
            'line1'         => $billing?->line1,
            'line2'         => $billing?->line2,
            'city'          => $billing?->city,
            'tax_id'        => $billing?->tax_id,
            'tax_id_kind'   => $billing?->tax_id_kind,
            'business_name' => $billing?->business_name,
            'buyer_name'    => $user->name,
        ];
        $calc = TaxCalculator::calculate(
            $items,
            [
                'country'     => $address['country'],
                'region'      => $address['region'],
                'tax_id'      => $address['tax_id'],
                'tax_id_kind' => $address['tax_id_kind'],
            ],
            $currency,
        );
        // Preserve meta on line items so ActivateSubscription can recover
        // plan_id / cycle / addon_id / qty when the invoice is later paid.
        $calc['line_items'] = array_map(function ($li, $idx) use ($items) {
            $li['meta'] = $items[$idx]['meta'] ?? [];
            return $li;
        }, $calc['line_items'], array_keys($calc['line_items']));

        $invoice = InvoiceService::issue($user, $calc, $address);
        $invoice->forceFill(['status' => 'pending', 'paid_at' => null])->save();
        return $invoice;
    }

    /**
     * Record the leftover value forfeited by a mid-cycle full-price
     * upgrade so an admin can optionally grant credit later. Best-effort:
     * a failure here must never roll back the activation. Skips creating a
     * row when there is nothing to review (no leftover days and no carried
     * add-ons).
     */
    protected function captureCreditReview(Subscription $old, Subscription $new, \Carbon\Carbon $now): void
    {
        try {
            $oldEnd = \Carbon\Carbon::parse($old->current_period_end);
            $leftoverDays = $oldEnd->isFuture()
                ? (int) ceil($now->floatDiffInDays($oldEnd, false))
                : 0;
            $leftoverDays = max(0, $leftoverDays);

            $addonsSnapshot = [];
            foreach ($old->addons()->with('addon')->get() as $sa) {
                $addonsSnapshot[] = [
                    'addon_id' => (int) $sa->addon_id,
                    'name'     => $sa->addon->name ?? ('Add-on #' . $sa->addon_id),
                    'qty'      => (int) ($sa->qty ?? 1),
                ];
            }

            // Nothing to review — don't create noise.
            if ($leftoverDays <= 0 && count($addonsSnapshot) === 0) {
                return;
            }

            // Add-ons share the subscription period, so their leftover time
            // equals the leftover plan days when any add-on is carried.
            $leftoverAddonDays = count($addonsSnapshot) > 0 ? $leftoverDays : 0;

            $review = SubscriptionCreditReview::create([
                'user_id'             => $new->user_id,
                'subscription_id'     => $new->id,
                'old_subscription_id' => $old->id,
                'old_plan_id'         => $old->plan_id,
                'new_plan_id'         => $new->plan_id,
                'leftover_days'       => $leftoverDays,
                'leftover_addon_days' => $leftoverAddonDays,
                'addons_snapshot'     => $addonsSnapshot,
                'currency'            => $new->currency,
                'status'              => 'pending',
            ]);

            // Best-effort team ping so the review queue isn't only pull-based.
            try {
                app(\App\Modules\Common\Services\NotificationService::class)->systemAlert(
                    'Upgrade leftover credit review',
                    'A full-price upgrade left ' . $leftoverDays . ' unused day(s)'
                        . (count($addonsSnapshot) ? ' plus ' . count($addonsSnapshot) . ' carried add-on(s)' : '')
                        . ' on the old plan. Review whether to grant credit.',
                    'info',
                    [
                        'user_id'     => $new->user_id,
                        'review_id'   => $review->id,
                        'leftover'    => $leftoverDays . 'd',
                    ],
                    \App\Services\Integrations\IntegrationKeySettings::ALERT_CATEGORY_PAYMENT,
                );
            } catch (\Throwable $e) {
                Log::warning('Credit-review alert failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Log::warning('captureCreditReview failed: ' . $e->getMessage(), [
                'old_subscription_id' => $old->id ?? null,
                'new_subscription_id' => $new->id ?? null,
            ]);
        }
    }

    protected function sendReceipt(Invoice $invoice): void
    {
        try {
            $email = optional($invoice->user)->email;
            if (!$email) return;
            $pdf = InvoiceService::renderPdf($invoice);
            \App\Modules\Common\Services\Emailer::send('billing.receipt', $email, [
                'invoice_number' => $invoice->number,
                'amount'         => number_format($invoice->grand_total_minor / 100, 2),
                'currency'       => $invoice->currency,
            ], [
                'user'        => $invoice->user_id,
                'related'     => $invoice,
                'attachments' => [[
                    'data' => $pdf,
                    'name' => $invoice->number . '.pdf',
                    'mime' => 'application/pdf',
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Receipt email failed: ' . $e->getMessage(), ['invoice' => $invoice->number]);
        }
    }
}
