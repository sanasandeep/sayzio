<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CoinPackage;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoinPackageController extends Controller
{
    public function index()
    {
        $packages = CoinPackage::with('prices')->ordered()->get();
        return view('admin.coin-packages.index', compact('packages'));
    }

    public function create()
    {
        $package = new CoinPackage(['status' => 'active']);
        return view('admin.coin-packages.create', compact('package'));
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
            'coin_amount' => $data['coin_amount'],
            'bonus_coins' => $data['bonus_coins'] ?? 0,
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'] ?? 0,
        ]);
        $this->syncPrices($package, $minor);
        $this->syncOriginalPrices($package, $this->extractOriginalMinor($data));

        return redirect()->route('admin.coin-packages.index')->with('success', 'Coin package created.');
    }

    public function edit(CoinPackage $coinPackage)
    {
        $package = $coinPackage;
        return view('admin.coin-packages.edit', compact('package'));
    }

    public function update(Request $request, CoinPackage $coinPackage)
    {
        $data = $this->validated($request);
        $minor = $this->extractMinor($data);
        $coinPackage->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'coin_amount' => $data['coin_amount'],
            'bonus_coins' => $data['bonus_coins'] ?? 0,
            'status'      => $data['status'],
            'sort_order'  => $data['sort_order'] ?? 0,
        ]);
        $this->syncPrices($coinPackage, $minor);
        $this->syncOriginalPrices($coinPackage, $this->extractOriginalMinor($data));
        return redirect()->route('admin.coin-packages.index')->with('success', 'Coin package updated.');
    }

    public function archive(CoinPackage $coinPackage)
    {
        $coinPackage->update(['is_archived' => !$coinPackage->is_archived]);
        return back()->with('success', $coinPackage->is_archived ? 'Package archived.' : 'Package restored.');
    }

    public function destroy(CoinPackage $coinPackage)
    {
        $coinPackage->prices()->delete();
        $coinPackage->delete();
        return redirect()->route('admin.coin-packages.index')->with('success', 'Coin package deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'coin_amount' => 'required|integer|min:1',
            'bonus_coins' => 'nullable|integer|min:0',
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
        $slug = $base;
        $i = 2;
        while (CoinPackage::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
