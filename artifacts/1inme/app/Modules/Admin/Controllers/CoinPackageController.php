<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\CoinPurchaseAllocation;
use App\Modules\Admin\Support\BillingFxRate;
use App\Modules\Common\Support\PricingPageCache;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoinPackageController extends Controller
{
    public function index()
    {
        $packages = CoinPackage::with('prices')->ordered()->get();
        $fxRate = BillingFxRate::get();
        return view('admin.coin-packages.index', compact('packages', 'fxRate'));
    }

    /**
     * Persist the admin-editable INR-per-USD exchange rate
     * (`billing.fx_rate_inr` app setting). Used by CoinPackagesSeeder for
     * new packages and as a computed-INR hint on the package forms.
     */
    public function updateFxRate(Request $request)
    {
        $data = $request->validate([
            'fx_rate_inr' => 'required|numeric|gt:0|max:10000',
        ]);
        BillingFxRate::put((float) $data['fx_rate_inr']);

        return redirect()->route('admin.coin-packages.index')
            ->with('success', 'INR exchange rate updated to ₹' . rtrim(rtrim(number_format((float) $data['fx_rate_inr'], 4, '.', ''), '0'), '.') . '/$1.');
    }

    /**
     * Admin-only revenue-split report: per-purchase allocation snapshots
     * (API budget vs platform margin) with an optional date range filter
     * and per-currency aggregates. Nothing here is ever user-facing.
     */
    public function allocations(Request $request)
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);
        $from = !empty($data['from']) ? \Carbon\Carbon::parse($data['from'])->startOfDay() : null;
        $to   = !empty($data['to'])   ? \Carbon\Carbon::parse($data['to'])->endOfDay()   : null;

        $base = CoinPurchaseAllocation::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->where('created_at', '<=', $to));

        // Per-currency aggregates over the filtered range.
        $totals = (clone $base)
            ->selectRaw('currency, COUNT(*) AS purchases, SUM(amount_minor) AS amount_minor, SUM(api_budget_minor) AS api_budget_minor, SUM(margin_minor) AS margin_minor, SUM(coins) AS coins')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $rows = (clone $base)
            ->with(['user:id,name,email', 'package:id,name,slug', 'invoice:id,number'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.coin-packages.allocations', [
            'rows'   => $rows,
            'totals' => $totals,
            'from'   => $data['from'] ?? null,
            'to'     => $data['to'] ?? null,
        ]);
    }

    public function create()
    {
        $package = new CoinPackage(['status' => 'active']);
        $fxRate = BillingFxRate::get();
        return view('admin.coin-packages.create', compact('package', 'fxRate'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $minor = $this->extractMinor($data);

        $package = CoinPackage::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'best_for'    => $data['best_for'] ?? null,
            'coin_amount' => $data['coin_amount'],
            'bonus_coins' => $data['bonus_coins'] ?? 0,
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'] ?? 0,
            'api_budget_pct' => isset($data['api_budget_pct']) && $data['api_budget_pct'] !== null
                ? round((float) $data['api_budget_pct'], 2) : null,
        ]);
        $this->syncPrices($package, $minor);
        $this->syncOriginalPrices($package, $this->extractOriginalMinor($data));
        PricingPageCache::flush();

        return redirect()->route('admin.coin-packages.index')->with('success', 'Coin package created.');
    }

    public function edit(CoinPackage $coinPackage)
    {
        $package = $coinPackage;
        $fxRate = BillingFxRate::get();
        return view('admin.coin-packages.edit', compact('package', 'fxRate'));
    }

    public function update(Request $request, CoinPackage $coinPackage)
    {
        $data = $this->validated($request);
        $minor = $this->extractMinor($data);
        $coinPackage->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'best_for'    => $data['best_for'] ?? null,
            'coin_amount' => $data['coin_amount'],
            'bonus_coins' => $data['bonus_coins'] ?? 0,
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'] ?? 0,
            'api_budget_pct' => isset($data['api_budget_pct']) && $data['api_budget_pct'] !== null
                ? round((float) $data['api_budget_pct'], 2) : null,
        ]);
        $this->syncPrices($coinPackage, $minor);
        $this->syncOriginalPrices($coinPackage, $this->extractOriginalMinor($data));
        PricingPageCache::flush();
        return redirect()->route('admin.coin-packages.index')->with('success', 'Coin package updated.');
    }

    public function archive(CoinPackage $coinPackage)
    {
        $coinPackage->update(['is_archived' => !$coinPackage->is_archived]);
        PricingPageCache::flush();
        return back()->with('success', $coinPackage->is_archived ? 'Package archived.' : 'Package restored.');
    }

    public function destroy(CoinPackage $coinPackage)
    {
        $coinPackage->prices()->delete();
        $coinPackage->delete();
        PricingPageCache::flush();
        return redirect()->route('admin.coin-packages.index')->with('success', 'Coin package deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'best_for'    => 'nullable|string|max:100',
            'coin_amount' => 'required|integer|min:1',
            'bonus_coins' => 'nullable|integer|min:0',
            // Hidden internal allocation: % of price budgeted for API costs.
            // Margin is always 100 − this. Admin-only, never user-facing.
            'api_budget_pct' => 'nullable|numeric|min:0|max:100',
            'status'      => 'required|in:active,inactive',
            'sort_order'  => 'nullable|integer|min:0',
            // Per-currency price in MINOR units (cents/paise).
            'price_usd'   => 'required|integer|min:0',
            'price_inr'   => 'required|integer|min:0',
            // Optional original ("compare-at") price in MINOR units. Blank or
            // zero clears the strike-off price for that currency.
            'original_price_usd' => 'nullable|integer|min:0',
            'original_price_inr' => 'nullable|integer|min:0',
        ]);
    }

    private function extractMinor(array $data): array
    {
        return [
            'USD' => (int) $data['price_usd'],
            'INR' => (int) $data['price_inr'],
        ];
    }

    private function extractOriginalMinor(array $data): array
    {
        return [
            'USD' => (int) ($data['original_price_usd'] ?? 0),
            'INR' => (int) ($data['original_price_inr'] ?? 0),
        ];
    }

    private function syncPrices(CoinPackage $pkg, array $minor): void
    {
        // Coin packages are one-time purchases; we re-use the
        // 'monthly' billing cycle slot so PricingResolver finds them.
        foreach ($minor as $currency => $amount) {
            PricingResolver::upsertFromMinor($pkg, $currency, 'monthly', $amount);
        }
    }

    /**
     * Persist (or clear) the optional original/compare-at price per currency
     * under the dedicated `compare` billing-cycle slot. A blank/zero amount
     * removes the strike-off price for that currency.
     */
    private function syncOriginalPrices(CoinPackage $pkg, array $minor): void
    {
        foreach ($minor as $currency => $amount) {
            if ($amount > 0) {
                PricingResolver::upsertFromMinor($pkg, $currency, CoinPackage::COMPARE_CYCLE, $amount);
            } else {
                $pkg->prices()
                    ->where('currency', $currency)
                    ->where('billing_cycle', CoinPackage::COMPARE_CYCLE)
                    ->delete();
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        return \App\Support\UniqueSuffix::resolve(CoinPackage::query(), $base);
    }
}
