<?php

namespace App\Modules\Api\Controllers;

use App\Events\SubscriptionActivated;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\RevenueCatGrant;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mobile billing endpoints powered by RevenueCat.
 *
 *   GET  /api/v1/billing/plans
 *       Active plan + addon catalogue priced for the signed-in user.
 *       Each plan exposes its `slug` which the mobile client uses as
 *       the RevenueCat entitlement lookup_key (one entitlement per
 *       plan slug — see ./references/initial-setup.md in the
 *       revenuecat skill).
 *
 *   POST /api/v1/billing/revenuecat/activate
 *       Called by the mobile client after `Purchases.purchasePackage`
 *       (or `Purchases.restorePurchases`) succeeds. Fetches the
 *       authoritative subscriber from RevenueCat's REST API, verifies
 *       the entitlement is currently active, then mutates the user's
 *       plan_id/billing_cycle and dispatches SubscriptionActivated
 *       (which issues a tax invoice + emails a receipt). Idempotent on
 *       RevenueCat's `original_transaction_id`.
 */
class RevenueCatBillingController extends Controller
{
    use ApiResponses;

    /**
     * Plan + addon catalogue, priced in the user's currency. Returned
     * shape mirrors the web upgrade page so the mobile client can
     * render features/cycles without a second round-trip.
     */
    public function plans(Request $request)
    {
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);

        // Mirror the web /pricing page: pre-compute BOTH currencies so the
        // mobile client can flip USD ⇄ INR instantly client-side (no
        // round-trip) just like the web switcher. `currency` is the
        // currently-resolved default; `prices[CUR]` carries every cell.
        $currencies = ['USD', 'INR'];

        $pricePair = function ($priceable, string $cur): array {
            $m = PricingResolver::priceForCurrency($priceable, $cur, 'monthly');
            $a = PricingResolver::priceForCurrency($priceable, $cur, 'annual');
            return [
                'monthly' => [
                    'amount_minor' => (int) ($m['amount_minor'] ?? 0),
                    'formatted'    => $m['formatted'] ?? null,
                ],
                'annual'  => [
                    'amount_minor' => (int) ($a['amount_minor'] ?? 0),
                    'formatted'    => $a['formatted'] ?? null,
                ],
            ];
        };

        $plans = Plan::query()
            ->public()
            ->where('status', 'active')
            ->where('is_archived', false)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function (Plan $p) use ($user, $currency, $currencies, $pricePair) {
                $prices = [];
                foreach ($currencies as $cur) {
                    $prices[$cur] = $pricePair($p, $cur);
                }
                return [
                    'id'           => $p->id,
                    'slug'         => $p->slug,
                    'name'         => $p->name,
                    'description'  => $p->description,
                    'features'     => is_array($p->features) ? array_values($p->features) : [],
                    'currency'     => $currency,
                    'is_default'   => (bool) $p->is_default,
                    'is_popular'   => (bool) $p->is_popular,
                    'is_current'   => $user && (int) $user->plan_id === (int) $p->id,
                    'features_map' => is_array($p->features) ? $p->features : [],
                    'trial_days'   => (int) ($p->trial_days ?? 0),
                    // Backward-compat: resolved-currency prices kept so older
                    // mobile builds keep working. `prices` is the new map.
                    'monthly'      => $prices[$currency]['monthly'],
                    'annual'       => $prices[$currency]['annual'],
                    'prices'       => $prices,
                ];
            })->all();

        $addons = Addon::query()
            ->where('status', 'active')
            ->where('is_archived', false)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function (Addon $a) use ($currency, $currencies, $pricePair) {
                $prices = [];
                foreach ($currencies as $cur) {
                    $prices[$cur] = $pricePair($a, $cur);
                }
                return [
                    'id'          => $a->id,
                    'slug'        => $a->slug,
                    'name'        => $a->name,
                    'description' => $a->description,
                    'type'        => $a->type,
                    'currency'    => $currency,
                    'monthly'     => $prices[$currency]['monthly'],
                    'annual'      => $prices[$currency]['annual'],
                    'prices'      => $prices,
                ];
            })->all();

        // Premium feature catalogue with plain-English copy is owned
        // by the API so the mobile Premium Features screen can render
        // exactly the same descriptions the web /premium-features page
        // shows — without the mobile bundle duplicating strings.
        $planModels = Plan::query()
            ->public()
            ->where('status', 'active')->where('is_archived', false)->get();
        $unlocks = \App\Modules\Common\Support\PremiumFeatures::unlocksByFeature($planModels);
        $premiumFeatures = collect(\App\Modules\Common\Support\PremiumFeatures::catalogue())
            ->map(fn ($e) => $e + ['unlocked_by' => $unlocks[$e['key']] ?? []])
            ->values()->all();

        return $this->ok([
            'currency'         => $currency,
            'currencies'       => $currencies,
            'plans'            => $plans,
            'addons'           => $addons,
            'premium_features' => $premiumFeatures,
        ]);
    }

    /**
     * Persist a manual USD ⇄ INR currency choice for the signed-in user,
     * mirroring the web /pricing switcher (POST /pricing/currency). The
     * choice is stored on `users.preferred_currency` (when the user has no
     * billing country — country always wins, same as web) so it follows
     * them across devices and is honoured by subsequent `priceFor()` calls
     * during purchase/activation. The plans endpoint already returns both
     * currencies so the UI flips instantly; this only persists the pick.
     */
    public function setCurrency(Request $request)
    {
        $data = $request->validate([
            'currency' => 'required|in:USD,INR',
        ]);

        PricingResolver::rememberManualChoice($data['currency'], $request->user());

        return $this->ok([
            'currency' => $data['currency'],
        ]);
    }

    /**
     * Verify a RevenueCat purchase and activate the matching plan.
     *
     * Body:
     *   plan_id  (required) plan to activate; the matching RevenueCat
     *            entitlement lookup_key MUST equal $plan->slug — the
     *            client cannot pick which entitlement to honour, that
     *            mapping is server-owned to prevent privilege escalation.
     *   cycle    (required) monthly|annual — informational only; the
     *            actual subscription period is enforced by RC.
     *
     * The RC subscriber is fetched server-side using REVENUECAT_REST_API_KEY
     * (the v1 secret key from the project Dashboard → API keys → "Secret
     * API Key"). This is not the mobile public SDK key.
     */
    public function activate(Request $request)
    {
        $data = $request->validate([
            'plan_id'     => 'required|integer|exists:plans,id',
            'cycle'       => 'required|in:monthly,annual',
            // Accepted for forward-compat but ignored — see entitlementKey.
            'entitlement' => 'nullable|string|max:190',
        ]);

        $user = $request->user();
        $appUserId = (string) $user->id;
        $plan = Plan::findOrFail($data['plan_id']);
        // Server-owned mapping: the entitlement we look up is *always* the
        // plan slug. If the client passed an entitlement and it doesn't
        // match the requested plan, refuse — that's a privilege-escalation
        // attempt (active "basic" entitlement, asking us to activate "pro").
        $entitlementKey = (string) $plan->slug;
        if (!empty($data['entitlement']) && $data['entitlement'] !== $entitlementKey) {
            return $this->fail(
                'Entitlement does not match the requested plan.',
                422,
                'entitlement_mismatch',
            );
        }

        $secret = (string) (env('REVENUECAT_REST_API_KEY')
            ?: config('services.revenuecat.api_key'));
        if ($secret === '') {
            return $this->fail('RevenueCat is not configured on the server.', 503);
        }

        // Fetch the authoritative subscriber from RevenueCat's REST API.
        // This is the only source of truth — never trust the client's
        // claim of an entitlement directly. Docs:
        // https://www.revenuecat.com/reference/subscribers
        try {
            $resp = Http::withToken($secret)
                ->acceptJson()
                ->withHeaders(['X-Platform' => 'ios'])
                ->timeout(10)
                ->get("https://api.revenuecat.com/v1/subscribers/{$appUserId}");
        } catch (\Throwable $e) {
            Log::warning('RevenueCat fetch failed', ['user_id' => $user->id, 'err' => $e->getMessage()]);
            return $this->fail('Could not reach RevenueCat. Please try again.', 502);
        }

        if (!$resp->successful()) {
            Log::warning('RevenueCat returned non-2xx', [
                'user_id' => $user->id, 'status' => $resp->status(),
            ]);
            return $this->fail('RevenueCat verification failed.', 502);
        }

        $subscriber  = (array) ($resp->json('subscriber') ?? []);
        $entitlements = (array) ($subscriber['entitlements'] ?? []);
        $entitlement  = $entitlements[$entitlementKey] ?? null;

        if (!$entitlement) {
            return $this->fail("No active RevenueCat entitlement for '{$entitlementKey}'.", 422);
        }

        $expiresRaw = $entitlement['expires_date'] ?? null;
        $expiresAt  = $expiresRaw ? Carbon::parse($expiresRaw) : null;
        if ($expiresAt && $expiresAt->isPast()) {
            return $this->fail('RevenueCat entitlement has expired.', 422);
        }

        $productId   = (string) ($entitlement['product_identifier'] ?? '');
        $purchasedAt = ($d = $entitlement['purchase_date'] ?? null) ? Carbon::parse($d) : null;

        $subscriptions = (array) ($subscriber['subscriptions'] ?? []);
        $sub   = $productId !== '' ? ($subscriptions[$productId] ?? null) : null;
        $store = (string) ($sub['store'] ?? 'unknown');

        // Idempotency key — derived from the *current* purchase event, NOT
        // the original_purchase_date. This way each renewal cycle gets its
        // own row, so we re-issue an invoice and refresh plan_expires_at.
        // The composite (user, entitlement, product, purchase_date) is
        // stable across re-deliveries of the same client call.
        $originalTxn = hash('sha256', implode('|', [
            $appUserId,
            $entitlementKey,
            $productId,
            (string) ($entitlement['purchase_date'] ?? ''),
        ]));

        // Idempotent fast-path; concurrent inserts are caught by the
        // unique constraint inside the transaction below.
        if (RevenueCatGrant::where('original_transaction_id', $originalTxn)->exists()) {
            return $this->ok([
                'ok'         => true,
                'idempotent' => true,
                'plan_id'    => $plan->id,
                'expires_at' => $expiresAt?->toIso8601String(),
            ]);
        }

        $currency = PricingResolver::currencyForUser($user);
        $priced   = PricingResolver::priceFor($plan, $user, $data['cycle']);

        try {
            DB::transaction(function () use ($user, $plan, $data, $currency, $priced, $expiresAt, $purchasedAt, $appUserId, $entitlementKey, $productId, $originalTxn, $store) {
                // Insert the grant first so a duplicate-key from a racing
                // request short-circuits before we mutate user state.
                RevenueCatGrant::create([
                    'user_id'                 => $user->id,
                    'plan_id'                 => $plan->id,
                    'cycle'                   => $data['cycle'],
                    'app_user_id'             => $appUserId,
                    'entitlement'             => $entitlementKey,
                    'product_identifier'      => $productId ?: null,
                    'original_transaction_id' => $originalTxn,
                    'store'                   => $store,
                    'purchased_at'            => $purchasedAt,
                    'expires_at'              => $expiresAt,
                ]);

                $user->forceFill([
                    'plan_id'         => $plan->id,
                    'billing_cycle'   => $data['cycle'],
                    // Trust RevenueCat's expiry where available — that's
                    // when the user actually loses access at the store.
                    'plan_expires_at' => $expiresAt
                        ?: now()->addMonths($data['cycle'] === 'annual' ? 12 : 1),
                ])->save();

                SubscriptionActivated::dispatch(
                    $user,
                    [[
                        'label'        => $plan->name . ' (' . $data['cycle'] . ', RevenueCat)',
                        'amount_minor' => (int) ($priced['amount_minor'] ?? 0),
                        'quantity'     => 1,
                    ]],
                    $currency,
                    'revenuecat:' . $originalTxn,
                );
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Lost the race with a concurrent identical request — the
            // peer has already activated the same purchase event, so
            // surface the same idempotent response.
            return $this->ok([
                'ok'         => true,
                'idempotent' => true,
                'plan_id'    => $plan->id,
                'expires_at' => $expiresAt?->toIso8601String(),
            ]);
        }

        return $this->ok([
            'ok'         => true,
            'plan_id'    => $plan->id,
            'cycle'      => $data['cycle'],
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);
    }
}
