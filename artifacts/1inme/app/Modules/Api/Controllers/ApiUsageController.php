<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ApiUsageCounter;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Read-only developer API-usage summary for the 1inme-mobile app
 * (task #1403). Mirrors the meter shown on the web /user/api-keys page so
 * the mobile API-usage screen can render the same allowance / overage /
 * coin figures that drive the `api.usage_warning` alerts.
 */
class ApiUsageController extends Controller
{
    use ApiResponses;

    public function show(Request $request)
    {
        $user = $request->user();

        $apiAccess = (bool) $user->planFeatureEnabled('api_access');

        $allowance = (int) $user->getPlanFeature('api_calls_monthly', 0);
        $unlimited = $allowance < 0 || $allowance === PHP_INT_MAX;

        $period  = ApiUsageCounter::currentPeriod();
        $counter = ApiUsageCounter::where('user_id', $user->id)
            ->where('period', $period)
            ->first();

        $callsUsed    = (int) ($counter->calls_used ?? 0);
        $overageCalls = (int) ($counter->overage_calls ?? 0);
        $coinsSpent   = (int) ($counter->coins_spent ?? 0);

        // Percentage of the included allowance consumed (clamped 0–100).
        $percentUsed = (!$unlimited && $allowance > 0)
            ? min(100, (int) round($callsUsed / $allowance * 100))
            : 0;

        $wallet = app(WalletService::class);

        return $this->ok([
            'api_access'     => $apiAccess,
            'period'         => $period,
            // -1 = unlimited; never emit the raw PHP_INT_MAX bypass sentinel.
            'allowance'      => $unlimited ? -1 : $allowance,
            'unlimited'      => $unlimited,
            'calls_used'     => $callsUsed,
            'overage_calls'  => $overageCalls,
            'coins_spent'    => $coinsSpent,
            'percent_used'   => $percentUsed,
            'remaining'      => $unlimited ? null : max(0, $allowance - $callsUsed),
            'rate_per_min'   => \App\Support\PlanLimit::normalize((int) $user->getPlanFeature('api_rate_per_min', 0)),
            'calls_per_coin' => WalletService::apiOverageCallsPerCoin(),
            'wallet_enabled' => WalletService::isEnabled(),
            'coin_balance'   => $wallet->getBalance($user),
        ]);
    }
}
