<?php

namespace App\Modules\User\Controllers;

use App\Events\SubscriptionActivated;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Services\Billing\WalletService;
use App\Services\BillingCyclePreference;
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
        // Persist the visitor's billing-cycle choice across pages — a
        // visitor who picked Annual on /pricing should still be on
        // Annual when they land here via the menu / refresh / a return
        // visit, not just when they clicked a CTA carrying `?cycle=`.
        // See BillingCyclePreference for the resolution chain.
        $cycle = BillingCyclePreference::resolve($request);
        Cookie::queue(BillingCyclePreference::remember($cycle));
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        // Eager-load `prices` so PricingResolver doesn't re-query per row
        // (avoids the obvious N+1 on this page).
        $plans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->public()
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

        // Currency-aware tax helper. The upgrade page now flips USD/INR
        // instantly client-side (no reload), so every price-bearing element
        // needs both currencies — and their matching tax breakdowns —
        // pre-computed and embedded in the Alpine payload.
        $currencies = ['USD', 'INR'];
        $taxFor = function (int $amountMinor, string $cur) use ($billing, $hasAddress) {
            if (!$hasAddress || $amountMinor === 0) {
                return null;
            }
            return TaxCalculator::calculate(
                [['label' => 'Plan', 'amount_minor' => $amountMinor]],
                [
                    'country'     => $billing->country,
                    'region'      => $billing->region,
                    'tax_id'      => $billing->tax_id,
                    'tax_id_kind' => $billing->tax_id_kind,
                ],
                $cur,
            );
        };

        $plansPriced = $plans->map(function ($p) use ($user, $cycle, $currency, $currencies, $taxFor) {
            $monthly = PricingResolver::priceFor($p, $user, 'monthly');
            $annual  = PricingResolver::priceFor($p, $user, 'annual');
            $shown   = $cycle === 'annual' ? $annual : $monthly;

            $prices = [];
            $taxByCur = [];
            foreach ($currencies as $cur) {
                $m = PricingResolver::priceForCurrency($p, $cur, 'monthly');
                $a = PricingResolver::priceForCurrency($p, $cur, 'annual');
                $prices[$cur] = ['monthly' => $m, 'annual' => $a];
                $taxByCur[$cur] = [
                    'monthly' => $taxFor((int) ($m['amount_minor'] ?? 0), $cur),
                    'annual'  => $taxFor((int) ($a['amount_minor'] ?? 0), $cur),
                ];
            }

            return [
                'model'    => $p,
                'monthly'  => $monthly,
                'annual'   => $annual,
                'shown'    => $shown,
                'tax'      => $taxFor((int) $shown['amount_minor'], $currency),
                'prices'   => $prices,
                'taxByCur' => $taxByCur,
            ];
        });

        $addonsPriced = $addons->map(function ($a) use ($user, $cycle, $currency, $currencies, $taxFor) {
            $shown = PricingResolver::priceFor($a, $user, $cycle);

            $prices = [];
            $taxByCur = [];
            foreach ($currencies as $cur) {
                $priced = PricingResolver::priceForCurrency($a, $cur, $cycle);
                $prices[$cur] = [$cycle => $priced];
                $taxByCur[$cur] = [$cycle => $taxFor((int) ($priced['amount_minor'] ?? 0), $cur)];
            }

            return [
                'model'    => $a,
                'shown'    => $shown,
                'tax'      => $taxFor((int) $shown['amount_minor'], $currency),
                'prices'   => $prices,
                'taxByCur' => $taxByCur,
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
            'wallet_enabled' => WalletService::isEnabled(),
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
        //   1. holder of `user.subscriptions.activate_manually` (manual
        //      grant / ops tool), OR
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
        $canActivateManually = $actor
            && method_exists($actor, 'hasPermission')
            && $actor->hasPermission('user.subscriptions.activate_manually');
        if (!$canActivateManually && !$hasValidSignature) {
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

        // The /pricing switcher flips currency instantly client-side and
        // pings this endpoint only to persist the choice — it expects no
        // body and must not be sent on a full-page redirect chase. Plain
        // form posts (e.g. /user/upgrade, the landing teaser) still get a
        // normal back() redirect so the reload reflects the new currency.
        if ($request->ajax() || $request->wantsJson()) {
            return response()->noContent();
        }

        return back();
    }
}
