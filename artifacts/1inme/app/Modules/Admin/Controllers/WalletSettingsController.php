<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Services\Billing\WalletService;
use Illuminate\Http\Request;

/**
 * Admin settings for the coin wallet:
 *   - Master toggle (wallet.enabled).
 *   - Coin-to-currency conversion rate per supported currency.
 *
 * The rate is "coins-per-currency-unit" — e.g. 100 means 1 USD = 100
 * coins. It's used as advisory copy on the Buy Coins page; package
 * prices remain authoritative.
 */
class WalletSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.wallet-settings.edit', [
            'enabled'          => WalletService::isEnabled(),
            'rates'            => WalletService::rates(),
            'apiOveragePerCoin' => WalletService::apiOverageCallsPerCoin(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'rates'   => 'array',
            'rates.USD' => 'nullable|numeric|min:0.0001',
            'rates.INR' => 'nullable|numeric|min:0.0001',
            'api_overage_calls_per_coin' => 'nullable|integer|min:1',
        ]);

        AppSetting::put(WalletService::FEATURE_KEY, $request->boolean('enabled'));

        $rates = ['USD' => 100, 'INR' => 1];
        foreach (($data['rates'] ?? []) as $cur => $val) {
            if ($val === null || $val === '') continue;
            $cur = strtoupper((string) $cur);
            if (in_array($cur, ['USD', 'INR'], true)) {
                $rates[$cur] = (float) $val;
            }
        }
        AppSetting::put(WalletService::RATE_KEY, $rates);

        $overage = (int) ($data['api_overage_calls_per_coin'] ?? WalletService::API_OVERAGE_DEFAULT);
        AppSetting::put(WalletService::API_OVERAGE_KEY, $overage > 0 ? $overage : WalletService::API_OVERAGE_DEFAULT);

        return redirect()->route('admin.wallet-settings.edit')
            ->with('success', 'Wallet settings saved.');
    }
}
