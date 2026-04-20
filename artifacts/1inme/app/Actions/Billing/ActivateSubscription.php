<?php

namespace App\Actions\Billing;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\SubscriptionAddon;
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
            // Fail-safe: invoice is already marked paid but not linked to a
            // subscription. Don't silently create a new one — that's a data
            // inconsistency that needs a human to look at.
            if ($fresh->status === 'paid' && !$fresh->subscription_id) {
                Log::error('Invoice already paid but missing subscription_id', [
                    'invoice_id' => $fresh->id, 'invoice_number' => $fresh->number,
                ]);
                throw new \RuntimeException("Invoice {$fresh->number} is already marked paid but has no subscription; please investigate.");
            }

            $items   = is_array($fresh->line_items) ? $fresh->line_items : [];
            $planId  = null;
            $cycle   = 'monthly';
            $addons  = [];
            foreach ($items as $item) {
                $meta = $item['meta'] ?? [];
                if (($meta['kind'] ?? null) === 'plan') {
                    $planId = (int) ($meta['plan_id'] ?? 0);
                    $cycle  = (string) ($meta['cycle'] ?? 'monthly');
                } elseif (($meta['kind'] ?? null) === 'addon') {
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
            $end    = $now->copy()->addMonths($months);

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
                'plan_expires_at' => $end,
            ])->save();

            $fresh->forceFill([
                'subscription_id' => $subscription->id,
                'gateway'         => $gateway,
                'status'          => 'paid',
                'paid_at'         => $now,
            ])->save();

            $this->sendReceipt($fresh);

            return $subscription;
        });
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

    protected function sendReceipt(Invoice $invoice): void
    {
        try {
            $email = optional($invoice->user)->email;
            if (!$email) return;
            $pdf = InvoiceService::renderPdf($invoice);
            Mail::raw(
                "Thanks for your payment.\n\nInvoice: {$invoice->number}\nAmount: "
                . number_format($invoice->grand_total_minor / 100, 2) . " {$invoice->currency}\n\n"
                . "Your tax invoice is attached (and also available in your billing history).",
                function ($m) use ($email, $invoice, $pdf) {
                    $m->to($email)
                      ->subject("Receipt {$invoice->number}")
                      ->attachData($pdf, $invoice->number . '.pdf', ['mime' => 'application/pdf']);
                }
            );
        } catch (\Throwable $e) {
            Log::warning('Receipt email failed: ' . $e->getMessage(), ['invoice' => $invoice->number]);
        }
    }
}
