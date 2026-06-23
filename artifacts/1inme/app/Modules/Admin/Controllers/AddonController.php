<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddonController extends Controller
{
    public function index()
    {
        $addons = Addon::with('plans')->ordered()->get();
        return view('admin.addons.index', compact('addons'));
    }

    public function create()
    {
        $plans = Plan::ordered()->get();
        return view('admin.addons.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $planIds = $data['plan_ids'] ?? [];
        unset($data['plan_ids']);

        $minor = $this->extractMinor($data);
        foreach ($minor as $k => $v) { $data[$k] = $v / 100; }

        $addon = Addon::create($data);
        $addon->plans()->sync($planIds);
        $this->syncPriceTable($addon, $minor);

        return redirect()->route('admin.addons.index')
            ->with('success', 'Addon created successfully.');
    }

    public function edit(Addon $addon)
    {
        $plans = Plan::ordered()->get();
        $attachedPlanIds = $addon->plans()->pluck('plans.id')->all();
        return view('admin.addons.edit', compact('addon', 'plans', 'attachedPlanIds'));
    }

    public function update(Request $request, Addon $addon)
    {
        $data = $this->validated($request);
        $planIds = $data['plan_ids'] ?? [];
        unset($data['plan_ids']);

        $minor = $this->extractMinor($data);
        foreach ($minor as $k => $v) { $data[$k] = $v / 100; }

        $addon->update($data);
        $addon->plans()->sync($planIds);
        $this->syncPriceTable($addon, $minor);

        return redirect()->route('admin.addons.index')
            ->with('success', 'Addon updated successfully.');
    }

    /** Pull the four MINOR-unit price values out of validated input. */
    private function extractMinor(array $data): array
    {
        return [
            'monthly_price'           => (int) $data['monthly_price'],
            'annual_price'            => (int) $data['annual_price'],
            'monthly_price_secondary' => (int) $data['monthly_price_secondary'],
            'annual_price_secondary'  => (int) $data['annual_price_secondary'],
        ];
    }

    public function archive(Addon $addon)
    {
        $addon->update(['is_archived' => !$addon->is_archived]);
        return back()->with('success', $addon->is_archived
            ? 'Addon archived.'
            : 'Addon restored.');
    }

    public function destroy(Addon $addon)
    {
        $addon->plans()->detach();
        $addon->delete();
        return redirect()->route('admin.addons.index')
            ->with('success', 'Addon deleted.');
    }

    private function validated(Request $request): array
    {
        // Pricing fields are MINOR units (cents/paise). All four are
        // required (USD/INR × monthly/annual) so every addon has
        // explicit per-currency pricing.
        $rules = [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'type'          => 'required|in:' . implode(',', Addon::TYPES),
            'monthly_price' => 'required|integer|min:0',
            'annual_price'  => 'required|integer|min:0',
            'monthly_price_secondary' => 'required|integer|min:0',
            'annual_price_secondary'  => 'required|integer|min:0',
            'status'        => 'required|in:active,inactive',
            'sort_order'    => 'integer|min:0',
            'features'      => 'nullable|array',
            'plan_ids'      => 'nullable|array',
            'plan_ids.*'    => 'integer|exists:plans,id',
            // Coin-cost is optional — null means not coin-redeemable.
            'coin_cost'     => 'nullable|integer|min:0',
        ];
        $data = $request->validate($rules);

        // Coerce checkbox values inside features[*] to booleans.
        if (!empty($data['features']) && is_array($data['features'])) {
            foreach ($data['features'] as $k => $v) {
                if ($v === '1' || $v === 'on' || $v === 1 || $v === true) {
                    $data['features'][$k] = true;
                }
            }
        }
        return $data;
    }

    /**
     * Persist all four required price rows (USD/INR × monthly/annual)
     * into the polymorphic `prices` table from MINOR-unit input.
     */
    private function syncPriceTable(Addon $addon, array $minor): void
    {
        PricingResolver::upsertManyFromMinor($addon, [
            ['USD', 'monthly', $minor['monthly_price']],
            ['USD', 'annual',  $minor['annual_price']],
            ['INR', 'monthly', $minor['monthly_price_secondary']],
            ['INR', 'annual',  $minor['annual_price_secondary']],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Addon::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
