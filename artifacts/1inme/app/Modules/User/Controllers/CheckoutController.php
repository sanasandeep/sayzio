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
    /** Upper bound on per-addon quantity a buyer can purchase at once. */
    public const MAX_ADDON_QTY = 99;

    /**
     * Show the checkout cart for a chosen plan + cycle + optional addons.
     * Uses query params (?plan=X&cycle=monthly&addons[ID]=QTY) so the
     * pricing/upgrade page can link directly here. The legacy list shape
     * (?addons[]=ID, quantity 1) is still accepted.
     */
    public function show(Request $request, GatewayManager $gm)
    {
        $user  = $request->user();
        $plan  = Plan::active()->findOrFail((int) $request->query('plan'));
        $cycle = $request->query('cycle', 'monthly') === 'annual' ? 'annual' : 'monthly';
        $currency = PricingResolver::currencyForUser($user);

        // Parse requested addons to an [id => qty] map and keep only those
        // actually eligible (attached) to the chosen plan — eligibility is
        // enforced here, not just hidden in the UI.
        $qtyMap = $this->parseAddonQuantities($request->query('addons', []));
        $addons = $this->eligibleAddons($plan, $qtyMap);

        $items = [];
        $planPriced = PricingResolver::priceForCurrency($plan, $currency, $cycle);
        $normalMinor = (int) $planPriced['amount_minor'];
        // First-term introductory discount (new subscriptions only). Intro
        // applies here; renewals/upgrades always charge the full price.
        $intro = PricingResolver::introFor($plan, $currency, $cycle, $normalMinor);
        $planMinor = $intro ? (int) $intro['first_minor'] : $normalMinor;
        $items[] = [
            'label'        => $plan->name . ' (' . $cycle . ')'
                . ($intro ? ' — first ' . ($cycle === 'annual' ? 'year' : 'month') . ' intro' : ''),
            'amount_minor' => $planMinor,
            'quantity'     => 1,
            'meta'         => array_filter([
                'kind'    => 'plan',
                'plan_id' => $plan->id,
                'cycle'   => $cycle,
                'intro_discount' => $intro ? [
                    'normal_minor'     => $normalMinor,
                    'amount_off_minor' => (int) $intro['amount_off_minor'],
                    'percent_off'      => (int) $intro['percent_off'],
                    'type'             => $intro['type'],
                ] : null,
            ], fn ($v) => $v !== null),
        ];
        foreach ($this->addonItems($addons, $qtyMap, $currency, $cycle) as $addonItem) {
            $items[] = $addonItem;
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
            'gateway' => 'required|string|in:razorpay,stripe,paypal,cashfree,payumoney,offline',
            'plan_id' => 'required|integer|exists:plans,id',
            'cycle'   => 'required|in:monthly,annual',
            // Modern shape: addons[ID] = QTY. Values are quantities; the
            // keys (addon ids) are validated by the eligibility query below.
            'addons'  => 'array',
            'addons.*' => 'integer|min:1',
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

        $normalMinor = (int) PricingResolver::priceForCurrency($plan, $currency, $cycle)['amount_minor'];
        // First-term introductory discount (new subscriptions only). Intro
        // applies here; renewals/upgrades always charge the full price.
        $intro = PricingResolver::introFor($plan, $currency, $cycle, $normalMinor);
        $planMinor = $intro ? (int) $intro['first_minor'] : $normalMinor;
        $items = [[
            'label'        => $plan->name . ' (' . $cycle . ')'
                . ($intro ? ' — first ' . ($cycle === 'annual' ? 'year' : 'month') . ' intro' : ''),
            'amount_minor' => $planMinor,
            'quantity'     => 1,
            'meta'         => array_filter([
                'kind'    => 'plan',
                'plan_id' => $plan->id,
                'cycle'   => $cycle,
                'intro_discount' => $intro ? [
                    'normal_minor'     => $normalMinor,
                    'amount_off_minor' => (int) $intro['amount_off_minor'],
                    'percent_off'      => (int) $intro['percent_off'],
                    'type'             => $intro['type'],
                ] : null,
            ], fn ($v) => $v !== null),
        ]];
        $qtyMap = $this->parseAddonQuantities($data['addons'] ?? []);
        $eligible = $this->eligibleAddons($plan, $qtyMap);
        foreach ($this->addonItems($eligible, $qtyMap, $currency, $cycle) as $addonItem) {
            $items[] = $addonItem;
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

    /**
     * Normalize the `addons` request payload into an [addonId => qty] map.
     * Accepts both the modern associative shape (addons[ID]=QTY) and the
     * legacy list shape (addons[]=ID, quantity 1). Quantities are clamped
     * to 1..MAX_ADDON_QTY. Returns an empty array for empty/invalid input.
     *
     * @return array<int,int>
     */
    private function parseAddonQuantities($raw): array
    {
        $raw = (array) $raw;
        if (empty($raw)) {
            return [];
        }

        $map = [];
        if (array_is_list($raw)) {
            // Legacy ?addons[]=ID — each id counts as one unit.
            foreach ($raw as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $map[$id] = ($map[$id] ?? 0) + 1;
                }
            }
        } else {
            // Modern ?addons[ID]=QTY.
            foreach ($raw as $id => $qty) {
                $id = (int) $id;
                $qty = (int) $qty;
                if ($id > 0 && $qty > 0) {
                    $map[$id] = $qty;
                }
            }
        }

        foreach ($map as $id => $qty) {
            $map[$id] = max(1, min(self::MAX_ADDON_QTY, $qty));
        }

        return $map;
    }

    /**
     * Active, non-archived addons among the requested ids that are actually
     * attached (eligible) to $plan, in the plan's addon order. Ineligible
     * ids are silently dropped — eligibility is enforced server-side.
     *
     * @param array<int,int> $qtyMap
     * @return \Illuminate\Support\Collection<int,Addon>
     */
    private function eligibleAddons(Plan $plan, array $qtyMap)
    {
        if (empty($qtyMap)) {
            return collect();
        }

        return $plan->addons()
            ->whereIn('addons.id', array_keys($qtyMap))
            ->where('addons.status', 'active')
            ->where('addons.is_archived', false)
            ->get();
    }

    /**
     * Build invoice line items for the eligible addons at their requested
     * quantity. `amount_minor` is the per-unit price; `quantity` (and the
     * mirrored meta `qty`) carry the count so the tax calculator and
     * subscription activation both bill/grant the right amount.
     *
     * @param \Illuminate\Support\Collection<int,Addon> $addons
     * @param array<int,int> $qtyMap
     * @return array<int,array<string,mixed>>
     */
    private function addonItems($addons, array $qtyMap, string $currency, string $cycle): array
    {
        $items = [];
        foreach ($addons as $a) {
            $qty = $qtyMap[$a->id] ?? 1;
            $items[] = [
                'label'        => $a->name,
                'amount_minor' => (int) PricingResolver::priceForCurrency($a, $currency, $cycle)['amount_minor'],
                'quantity'     => $qty,
                'meta'         => ['kind' => 'addon', 'addon_id' => $a->id, 'qty' => $qty],
            ];
        }
        return $items;
    }

    /**
     * Re-render the offline (manual bank/UPI) payment instructions page for
     * an invoice the buyer already started checkout on. Side-effect free —
     * used so the buyer can return to the page (and after submitting their
     * UPI transaction reference).
     */
    public function offline(Request $request, Invoice $invoice, GatewayManager $gm)
    {
        $this->authorizeOfflineInvoice($request, $invoice);

        $adapter = $gm->for('offline');
        if (!$adapter instanceof \App\Services\Billing\Adapters\OfflineAdapter) {
            abort(404);
        }

        return view('user.checkout.offline', $adapter->offlineViewData($invoice));
    }

    /**
     * Persist the buyer-reported UPI transaction reference / UTR against the
     * invoice's offline payment attempt. Optional and best-effort — there is
     * no real-time validation; the admin matches it manually at approval.
     */
    public function offlineReference(Request $request, Invoice $invoice)
    {
        $this->authorizeOfflineInvoice($request, $invoice);

        $data = $request->validate([
            'upi_reference' => ['nullable', 'string', 'max:190'],
        ]);
        $reference = trim((string) ($data['upi_reference'] ?? ''));

        $attempt = $invoice->paymentAttempts()
            ->where('gateway', 'offline')
            ->orderByDesc('id')
            ->first();

        if ($attempt) {
            $raw = (array) ($attempt->raw_response ?? []);
            $raw['buyer_reference']    = $reference;
            $raw['buyer_reference_at'] = now()->toIso8601String();
            $attempt->update(['raw_response' => $raw]);
        }

        return redirect()->route('user.checkout.offline', $invoice)
            ->with('success', $reference !== ''
                ? 'Thanks — we recorded your transaction reference. Your plan activates once we confirm the payment.'
                : 'Saved.');
    }

    /**
     * Only the invoice owner may view/submit on the offline checkout page.
     * The {invoice} route-model binding accepts any id, so guard ownership
     * explicitly (workspace.owner only proves they own the workspace).
     */
    protected function authorizeOfflineInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless((int) $invoice->user_id === (int) $request->user()?->id, 403);
        abort_unless($invoice->gateway === 'offline', 404);
    }
}
