<?php

namespace App\Modules\User\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Invoice;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use App\Services\PricingResolver;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    /**
     * Show the checkout cart for a chosen plan + cycle + optional addons.
     * Uses query params (?plan=X&cycle=monthly&addons[]=Y) so the pricing
     * page can link directly here.
     */
    public function show(Request $request, GatewayManager $gm)
    {
        $user  = $request->user();
        $plan  = Plan::active()->findOrFail((int) $request->query('plan'));
        $cycle = $request->query('cycle', 'monthly') === 'annual' ? 'annual' : 'monthly';
        $currency = PricingResolver::currencyForUser($user);

        $addonIds = array_map('intval', (array) $request->query('addons', []));
        $addons = $addonIds
            ? Addon::whereIn('id', $addonIds)->where('status', 'active')->where('is_archived', false)->get()
            : collect();

        $items = [];
        $planPriced = PricingResolver::priceFor($plan, $user, $cycle);
        $items[] = [
            'label'        => $plan->name . ' (' . $cycle . ')',
            'amount_minor' => (int) $planPriced['amount_minor'],
            'quantity'     => 1,
            'meta'         => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => $cycle],
        ];
        foreach ($addons as $a) {
            $p = PricingResolver::priceFor($a, $user, $cycle);
            $items[] = [
                'label'        => $a->name,
                'amount_minor' => (int) $p['amount_minor'],
                'quantity'     => 1,
                'meta'         => ['kind' => 'addon', 'addon_id' => $a->id, 'qty' => 1],
            ];
        }

        // Preview the tax breakdown without persisting an invoice yet.
        $preview = \App\Services\TaxCalculator::calculate(
            $items,
            (function () use ($user) {
                $b = \App\Modules\User\Models\BillingAddress::where('user_id', $user->id)->first();
                return [
                    'country'     => $b?->country ?? ($user->country ?? null),
                    'region'      => $b?->region,
                    'tax_id'      => $b?->tax_id,
                    'tax_id_kind' => $b?->tax_id_kind,
                ];
            })(),
            $currency,
        );

        return view('user.checkout.show', [
            'plan'     => $plan,
            'addons'   => $addons,
            'cycle'    => $cycle,
            'currency' => $currency,
            'items'    => $items,
            'preview'  => $preview,
            'gateways' => $gm->enabledAdapters(),
        ]);
    }

    /**
     * Create a pending invoice and hand off to the selected gateway.
     * Stores the items so ActivateSubscription can later reconstitute
     * plan/addon/qty from the invoice alone.
     */
    public function handoff(Request $request, GatewayManager $gm)
    {
        $data = $request->validate([
            'gateway' => 'required|string|in:razorpay,stripe,paypal,cashfree,offline',
            'plan_id' => 'required|integer|exists:plans,id',
            'cycle'   => 'required|in:monthly,annual',
            'addons'  => 'array',
            'addons.*' => 'integer|exists:addons,id',
        ]);

        $user  = $request->user();
        $plan  = Plan::active()->findOrFail($data['plan_id']);
        $cycle = $data['cycle'];
        $currency = PricingResolver::currencyForUser($user);

        // Server-side enabled check. The form only renders enabled
        // gateways, but a direct POST must not bypass that.
        $enabledSlugs = array_map(fn($a) => $a->slug(), $gm->enabledAdapters());
        if (!in_array($data['gateway'], $enabledSlugs, true)) {
            return back()->with('error', 'That payment method is not available right now.');
        }

        $items = [[
            'label'        => $plan->name . ' (' . $cycle . ')',
            'amount_minor' => (int) PricingResolver::priceFor($plan, $user, $cycle)['amount_minor'],
            'quantity'     => 1,
            'meta'         => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => $cycle],
        ]];
        foreach ((array) ($data['addons'] ?? []) as $addonId) {
            $a = Addon::findOrFail($addonId);
            $items[] = [
                'label'        => $a->name,
                'amount_minor' => (int) PricingResolver::priceFor($a, $user, $cycle)['amount_minor'],
                'quantity'     => 1,
                'meta'         => ['kind' => 'addon', 'addon_id' => $a->id, 'qty' => 1],
            ];
        }

        $invoice = ActivateSubscription::issuePendingInvoice($user, $items, $currency);

        try {
            $adapter = $gm->for($data['gateway']);
            $result  = $adapter->createCheckout($invoice);
        } catch (NotImplementedException $e) {
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return redirect()->route('user.upgrade')
                ->with('error', $adapter->displayName() . ' is not available yet. Please choose another payment method.');
        } catch (\Throwable $e) {
            // Gateway API failure (network, auth, validation): cancel the
            // pending invoice, surface a friendly message, and let the
            // attempt logging inside the adapter carry the raw cause.
            \Illuminate\Support\Facades\Log::warning('Checkout handoff failed', [
                'gateway' => $data['gateway'], 'invoice' => $invoice->number, 'error' => $e->getMessage(),
            ]);
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return redirect()->route('user.upgrade')
                ->with('error', 'We couldn\'t start your payment with '
                    . ($adapter?->displayName() ?? $data['gateway'])
                    . '. Please try again or pick another payment method.');
        }

        if (($result['kind'] ?? null) === 'redirect') {
            return redirect()->away((string) $result['url']);
        }
        if (($result['kind'] ?? null) === 'view') {
            return view($result['view'], $result['data']);
        }
        return redirect()->route('user.invoices.pdf', $invoice);
    }
}
