<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\ApiUsageCounter;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Customer-facing API key management (task #1393).
 *
 * API keys are Sanctum personal access tokens stamped with
 * `client_kind = 'api_key'` so they can be told apart from the
 * first-party web/mobile session tokens (which are NOT metered). Calls
 * made with these keys count against the plan's monthly included
 * allowance and spend coins for any overage — see MeterApiUsage.
 *
 * Gated behind the `api_access` plan feature.
 */
class ApiKeyController extends Controller
{
    private const KIND = 'api_key';

    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user->planFeatureEnabled('api_access')) {
            return redirect()
                ->route('user.upgrade')
                ->with('error', 'API access is not included in your current plan. Upgrade to generate API keys.');
        }

        $keys = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->where('client_kind', self::KIND)
            ->orderByDesc('created_at')
            ->get();

        $allowance = (int) $user->getPlanFeature('api_calls_monthly', 0);
        $unlimited = $allowance < 0 || $allowance === PHP_INT_MAX;

        $counter = ApiUsageCounter::where('user_id', $user->id)
            ->where('period', ApiUsageCounter::currentPeriod())
            ->first();

        $callsUsed = (int) ($counter->calls_used ?? 0);

        // The metering period is a calendar month, so it resets at the
        // start of next month (see ApiUsageCounter::currentPeriod()).
        $periodReset = now()->startOfMonth()->addMonth();
        $daysLeft = (int) ceil(now()->floatDiffInDays($periodReset));

        // Percent of the included allowance consumed (capped at 100 for the
        // bar; overage is surfaced separately). Unlimited plans show no bar.
        $percentUsed = (!$unlimited && $allowance > 0)
            ? min(100, (int) round(($callsUsed / $allowance) * 100))
            : 0;

        return view('user.api-keys.index', [
            'keys'         => $keys,
            'allowance'    => $allowance,
            'unlimited'    => $unlimited,
            'callsUsed'    => $callsUsed,
            'percentUsed'  => $percentUsed,
            'periodReset'  => $periodReset,
            'daysLeft'     => $daysLeft,
            'overageCalls' => (int) ($counter->overage_calls ?? 0),
            'coinsSpent'   => (int) ($counter->coins_spent ?? 0),
            'rate'         => (int) $user->getPlanFeature('api_rate_per_min', 0),
            'callsPerCoin' => WalletService::apiOverageCallsPerCoin(),
            'walletEnabled' => WalletService::isEnabled(),
            'coinBalance'  => app(WalletService::class)->getBalance($user),
            // Surface the plaintext token exactly once after creation.
            'newToken'     => session('api_key_plain'),
            'newTokenName' => session('api_key_name'),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->planFeatureEnabled('api_access')) {
            return redirect()
                ->route('user.upgrade')
                ->with('error', 'API access is not included in your current plan. Upgrade to generate API keys.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        // Sensible cap so a single account can't mint unlimited keys.
        $existing = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->where('client_kind', self::KIND)
            ->count();
        if ($existing >= 25) {
            return back()->with('error', 'You have reached the maximum of 25 API keys. Revoke one to create another.');
        }

        $new = $user->createToken($data['name'], ['*']);

        // Stamp metadata so the key is distinguishable from session tokens
        // (and so MeterApiUsage knows to meter it).
        $new->accessToken->forceFill([
            'client_kind' => self::KIND,
            'device_label' => $data['name'],
            'created_ip'  => $request->ip(),
        ])->save();

        return redirect()
            ->route('user.api-keys.index')
            ->with('success', 'API key created. Copy it now — you will not be able to see it again.')
            ->with('api_key_plain', $new->plainTextToken)
            ->with('api_key_name', $data['name']);
    }

    public function destroy(Request $request, int $key)
    {
        $user = Auth::user();

        PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->where('client_kind', self::KIND)
            ->where('id', $key)
            ->delete();

        return back()->with('success', 'API key revoked.');
    }
}
