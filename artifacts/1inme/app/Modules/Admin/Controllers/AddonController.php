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

        $addon = Addon::create($data);
        $addon->plans()->sync($planIds);
        $this->syncPriceTable($addon, $data);

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

        $addon->update($data);
        $addon->plans()->sync($planIds);
        $this->syncPriceTable($addon, $data);

        return redirect()->route('admin.addons.index')
            ->with('success', 'Addon updated successfully.');
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
        $rules = [
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string',
            'type'          => 'required|in:' . implode(',', Addon::TYPES),
            'monthly_price' => 'required|numeric|min:0',
            'annual_price'  => 'required|numeric|min:0',
            'monthly_price_secondary' => 'nullable|numeric|min:0',
            'annual_price_secondary'  => 'nullable|numeric|min:0',
            'status'        => 'required|in:active,inactive',
            'sort_order'    => 'integer|min:0',
            'features'      => 'nullable|array',
            'plan_ids'      => 'nullable|array',
            'plan_ids.*'    => 'integer|exists:plans,id',
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

    /** Mirror legacy decimal columns into the polymorphic `prices` table. */
    private function syncPriceTable(Addon $addon, array $v): void
    {
        PricingResolver::upsertFromMajor($addon, 'USD', 'monthly', $v['monthly_price'] ?? null);
        PricingResolver::upsertFromMajor($addon, 'USD', 'annual',  $v['annual_price']  ?? null);
        PricingResolver::upsertFromMajor($addon, 'INR', 'monthly', $v['monthly_price_secondary'] ?? null);
        PricingResolver::upsertFromMajor($addon, 'INR', 'annual',  $v['annual_price_secondary']  ?? null);
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
