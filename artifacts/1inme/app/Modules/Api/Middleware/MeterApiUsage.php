<?php

namespace App\Modules\Api\Middleware;

use App\Modules\User\Models\ApiUsageCounter;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Monthly API-call metering for developer API keys (task #1393).
 *
 * Runs inside the authenticated `/api/v1` group, AFTER auth:sanctum.
 *
 * IMPORTANT: this only meters calls made with a developer **API key**
 * (personal access token stamped `client_kind = 'api_key'`). First-party
 * session tokens minted for the web/mobile apps authenticate through the
 * very same `auth:sanctum` guard, so metering every authenticated request
 * would wrongly burn ordinary app users' coins. Those are skipped.
 *
 * Each metered call:
 *   - is counted against the plan's monthly included allowance
 *     (`api_calls_monthly`; -1 / bypass = unlimited);
 *   - once the allowance is used up, is paid for with coins at the
 *     admin-set overage rate (1 coin buys N calls; the unused remainder
 *     of a purchased block is carried forward in the counter row);
 *   - is rejected with HTTP 402 + the unified {error} envelope when the
 *     allowance is exhausted and the wallet can't cover the overage.
 *
 * The counter row is locked first, then WalletService takes the wallet
 * lock — a consistent lock order across requests so concurrent calls
 * can't deadlock.
 */
class MeterApiUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // Only meter developer API-key tokens, never first-party app sessions.
        try {
            $token = $user->currentAccessToken();
        } catch (\Throwable) {
            $token = null;
        }
        if (!$token instanceof Model || ($token->client_kind ?? null) !== 'api_key') {
            return $next($request);
        }

        // Enforce plan-gated API access at the request path, not just at key
        // creation time. A user who downgrades to a plan without `api_access`
        // keeps any previously-minted keys, so we must reject their calls here
        // (otherwise they retain effective API access — and could even spend
        // coins on overage — after losing the feature).
        if (!$user->planFeatureEnabled('api_access')) {
            return response()->json([
                'error' => [
                    'message' => 'API access is not included in your current plan. Upgrade to a plan with API access to use API keys.',
                    'code'    => 'api_access_disabled',
                ],
            ], 403);
        }

        // Allowance: -1 (or bypass → PHP_INT_MAX) means unlimited.
        $allowance = (int) $user->getPlanFeature('api_calls_monthly', 0);
        $unlimited = $allowance < 0 || $allowance === PHP_INT_MAX;

        $callsPerCoin = WalletService::apiOverageCallsPerCoin();
        $wallet = app(WalletService::class);

        try {
            $denied = DB::transaction(function () use ($user, $allowance, $unlimited, $callsPerCoin, $wallet) {
                $period = ApiUsageCounter::currentPeriod();

                // Lock (or create) the period counter row first.
                $counter = ApiUsageCounter::where('user_id', $user->id)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->first();

                if (!$counter) {
                    ApiUsageCounter::create([
                        'user_id'                   => $user->id,
                        'period'                    => $period,
                        'calls_used'                => 0,
                        'overage_calls'             => 0,
                        'coins_spent'               => 0,
                        'prepaid_overage_remaining' => 0,
                    ]);
                    $counter = ApiUsageCounter::where('user_id', $user->id)
                        ->where('period', $period)
                        ->lockForUpdate()
                        ->first();
                }

                // Within the included allowance (or unlimited) — free call.
                if ($unlimited || ($counter->calls_used + 1) <= $allowance) {
                    $counter->forceFill(['calls_used' => $counter->calls_used + 1])->save();
                    return null;
                }

                // Overage. Consume a prepaid call if one is banked.
                if ($counter->prepaid_overage_remaining > 0) {
                    $counter->forceFill([
                        'calls_used'                => $counter->calls_used + 1,
                        'overage_calls'             => $counter->overage_calls + 1,
                        'prepaid_overage_remaining' => $counter->prepaid_overage_remaining - 1,
                    ])->save();
                    return null;
                }

                // Need to buy a fresh overage block with 1 coin.
                if (!WalletService::isEnabled()) {
                    return $this->limitDenial($allowance);
                }

                try {
                    $wallet->debit($user, 1, [
                        'reason' => 'API overage — ' . $callsPerCoin . ' calls',
                        'meta'   => ['kind' => 'api_overage', 'period' => $period, 'calls_per_coin' => $callsPerCoin],
                    ]);
                } catch (InsufficientCoinsException) {
                    return $this->coinDenial($allowance, $callsPerCoin);
                }

                // One coin bought `callsPerCoin` calls; this request uses one,
                // the rest is banked for subsequent calls this period.
                $counter->forceFill([
                    'calls_used'                => $counter->calls_used + 1,
                    'overage_calls'             => $counter->overage_calls + 1,
                    'coins_spent'               => $counter->coins_spent + 1,
                    'prepaid_overage_remaining' => max(0, $callsPerCoin - 1),
                ])->save();
                return null;
            });
        } catch (\Throwable $e) {
            // Never let a metering fault take down the API; fail open.
            report($e);
            return $next($request);
        }

        if (is_array($denied)) {
            return response()->json(
                ['error' => $denied['error']],
                $denied['status']
            );
        }

        return $next($request);
    }

    /**
     * Allowance exhausted and overage purchasing is unavailable (wallet off).
     *
     * @return array{status:int, error:array<string,mixed>}
     */
    private function limitDenial(int $allowance): array
    {
        return [
            'status' => 402,
            'error'  => [
                'message' => 'Monthly API-call allowance exceeded. Upgrade your plan for a higher allowance.',
                'code'    => 'api_quota_exceeded',
                'details' => ['allowance' => $allowance],
            ],
        ];
    }

    /**
     * Allowance exhausted and the wallet can't cover the overage.
     *
     * @return array{status:int, error:array<string,mixed>}
     */
    private function coinDenial(int $allowance, int $callsPerCoin): array
    {
        return [
            'status' => 402,
            'error'  => [
                'message' => 'Monthly API-call allowance exceeded and not enough coins to cover overage. Top up coins or upgrade your plan.',
                'code'    => 'api_quota_exceeded',
                'details' => ['allowance' => $allowance, 'calls_per_coin' => $callsPerCoin],
            ],
        ];
    }
}
