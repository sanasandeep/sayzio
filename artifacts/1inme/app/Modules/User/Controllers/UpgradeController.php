<?php

namespace App\Modules\User\Controllers;

use App\Events\SubscriptionActivated;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Services\PlanRecommender;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class UpgradeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $cycle = $request->query('cycle', 'monthly') === 'annual' ? 'annual' : 'monthly';
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        // Eager-load `prices` so PricingResolver doesn't re-query per row
        // (avoids the obvious N+1 on this page).
        $plans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()->get();
        $addons = Addon::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()->get();

        // Pull the buyer's billing address (if any) so we can show real
        // tax breakdowns instead of the "+ taxes as applicable" placeholder.
        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        $taxFor = function ($priced) use ($billing, $currency, $hasAddress) {
            if (!$hasAddress || (int) $priced['amount_minor'] === 0) {
                return null;
            }
            return TaxCalculator::calculate(
                [['label' => 'Plan', 'amount_minor' => (int) $priced['amount_minor']]],
                [
                    'country'     => $billing->country,
                    'region'      => $billing->region,
                    'tax_id'      => $billing->tax_id,
                    'tax_id_kind' => $billing->tax_id_kind,
                ],
                $currency,
            );
        };

        $plansPriced = $plans->map(function ($p) use ($user, $cycle, $taxFor) {
            $monthly = PricingResolver::priceFor($p, $user, 'monthly');
            $annual  = PricingResolver::priceFor($p, $user, 'annual');
            $shown   = $cycle === 'annual' ? $annual : $monthly;
            return [
                'model'   => $p,
                'monthly' => $monthly,
                'annual'  => $annual,
                'shown'   => $shown,
                'tax'     => $taxFor($shown),
            ];
        });

        $addonsPriced = $addons->map(function ($a) use ($user, $cycle, $taxFor) {
            $shown = PricingResolver::priceFor($a, $user, $cycle);
            return [
                'model'  => $a,
                'shown'  => $shown,
                'tax'    => $taxFor($shown),
            ];
        });

        $recommendation = PlanRecommender::for($user, $plans);

        return view('user.upgrade.show', [
            'plans'      => $plansPriced,
            'addons'     => $addonsPriced,
            'cycle'      => $cycle,
            'currency'   => $currency,
            'currencySource' => $currencySource,
            'user'       => $user,
            'hasAddress' => $hasAddress,
            'billing'    => $billing,
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Anonymous (or signed-in override) currency switcher.
     * Persists in the session so subsequent requests render the same currency.
     * Signed-in users with a country still see their country-default until they
     * change it on the profile page — this only affects the session preview.
     */
    /**
     * Activate a plan for the signed-in user and issue a tax invoice.
     * This is the successful-payment codepath that any gateway webhook
     * (or the manual "admin grant" flow) dispatches into — it updates
     * user.plan_id/billing_cycle and fires SubscriptionActivated, whose
     * listener runs TaxCalculator + InvoiceService::issue().
     */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'user_id'     => 'nullable|integer|exists:users,id',
            'plan_id'     => 'required|integer|exists:plans,id',
            'cycle'       => 'required|in:monthly,annual',
            'gateway_ref' => 'nullable|string|max:190',
            'signature'   => 'nullable|string',
        ]);

        $actor = $request->user();
        $isWebhook = $request->is('webhooks/billing/*') || !$actor;

        // Unauthenticated/webhook callers must always identify the buyer.
        if (!$actor && empty($data['user_id'])) {
            if ($isWebhook) {
                return response()->json(['error' => 'user_id required'], 422);
            }
            abort(422, 'user_id required');
        }

        // Authorization: this endpoint represents a verified payment-success
        // signal and must not be callable by ordinary users for their own
        // account (entitlement escalation). Accepted callers:
        //   1. super_admin (manual grant / ops tool), OR
        //   2. a signed gateway webhook carrying a valid HMAC of the
        //      (user_id|plan_id|cycle|gateway_ref) tuple, signed with
        //      config('billing.activation_secret').
        $secret = (string) config('billing.activation_secret', '');
        $expected = $secret !== '' ? hash_hmac('sha256',
            implode('|', [
                $data['user_id'] ?? $actor?->id,
                $data['plan_id'],
                $data['cycle'],
                $data['gateway_ref'] ?? '',
            ]),
            $secret,
        ) : null;
        $hasValidSignature = $expected && !empty($data['signature'])
            && hash_equals($expected, (string) $data['signature']);
        $isSuperAdmin = $actor && method_exists($actor, 'isSuperAdmin') && $actor->isSuperAdmin();
        if (!$isSuperAdmin && !$hasValidSignature) {
            if ($isWebhook) {
                return response()->json(['error' => 'invalid signature'], 403);
            }
            abort(403, 'Plan activation requires a verified payment signal.');
        }

        $targetId = $data['user_id'] ?? $actor->id;
        $user = \App\Modules\User\Models\User::findOrFail($targetId);
        $plan = Plan::findOrFail($data['plan_id']);
        $currency = PricingResolver::currencyForUser($user);
        $priced = PricingResolver::priceFor($plan, $user, $data['cycle']);

        $user->forceFill([
            'plan_id'         => $plan->id,
            'billing_cycle'   => $data['cycle'],
            'plan_expires_at' => now()->addMonths($data['cycle'] === 'annual' ? 12 : 1),
        ])->save();

        SubscriptionActivated::dispatch(
            $user,
            [[
                'label'        => $plan->name . ' (' . $data['cycle'] . ')',
                'amount_minor' => (int) $priced['amount_minor'],
                'quantity'     => 1,
            ]],
            $currency,
            $data['gateway_ref'] ?? null,
        );

        if ($isWebhook || $request->wantsJson()) {
            return response()->json(['ok' => true, 'plan_id' => $plan->id, 'user_id' => $user->id], 200);
        }
        return redirect()->route('user.upgrade')->with('success', 'Plan activated. Your tax invoice is available in your billing history.');
    }

    public function switchCurrency(Request $request)
    {
        $currency = strtoupper((string) $request->input('currency', 'USD'));
        if (!in_array($currency, ['USD', 'INR'], true)) {
            $currency = 'USD';
        }
        // Persists the choice in three places (where applicable):
        //   - session flag (this request and the rest of the session),
        //   - long-lived signed cookie (survives session expiry for
        //     anonymous visitors),
        //   - users.preferred_currency, if signed in and no profile
        //     country (so the choice follows them across devices).
        $cookie = PricingResolver::rememberManualChoice($currency, $request->user());
        Cookie::queue($cookie);
        return back();
    }
}
