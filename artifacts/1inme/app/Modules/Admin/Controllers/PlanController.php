<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('users')->ordered()->get();
        return view('admin.plans.index', compact('plans'));
    }

    public function show(Plan $plan)
    {
        $plan->loadCount('users');
        return view('admin.plans.show', compact('plan'));
    }

    public function create()
    {
        $addons = Addon::ordered()->get();
        $attachedAddonIds = [];
        return view('admin.plans.create', compact('addons', 'attachedAddonIds'));
    }

    public function archive(Plan $plan)
    {
        $plan->update(['is_archived' => !$plan->is_archived]);
        return back()->with('success', $plan->is_archived
            ? 'Plan archived. Existing subscribers continue, but new signups can no longer pick it.'
            : 'Plan restored.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = $this->uniqueSlug($validated['name']);
        $validated['is_popular'] = $request->boolean('is_popular');
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        if (!isset($validated['features'])) {
            $validated['features'] = $this->defaultFeatures();
        }

        // The form posts MINOR units; the legacy decimal columns on the
        // model still expect MAJOR units, so down-convert before persist.
        // The polymorphic prices table is then synced from the original
        // minor-unit values via syncPriceTable().
        $minor = [
            'monthly_price'           => (int) $validated['monthly_price'],
            'annual_price'            => (int) $validated['annual_price'],
            'monthly_price_secondary' => (int) $validated['monthly_price_secondary'],
            'annual_price_secondary'  => (int) $validated['annual_price_secondary'],
        ];
        foreach ($minor as $k => $v) {
            $validated[$k] = $v / 100;
        }

        $plan = Plan::create($validated);
        if ($plan->is_popular) {
            Plan::where('id', '!=', $plan->id)->where('is_popular', true)->update(['is_popular' => false]);
        }
        $plan->addons()->sync($addonIds);
        $this->syncPriceTable($plan, $minor);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        $addons = Addon::ordered()->get();
        $attachedAddonIds = $plan->addons()->pluck('addons.id')->all();
        return view('admin.plans.edit', compact('plan', 'addons', 'attachedAddonIds'));
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate($this->rules());
        $validated['is_popular'] = $request->boolean('is_popular');
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        // Convert MINOR-unit form input → MAJOR for legacy decimal columns,
        // keep the minor values for the prices-table sync.
        $minor = [
            'monthly_price'           => (int) $validated['monthly_price'],
            'annual_price'            => (int) $validated['annual_price'],
            'monthly_price_secondary' => (int) $validated['monthly_price_secondary'],
            'annual_price_secondary'  => (int) $validated['annual_price_secondary'],
        ];
        foreach ($minor as $k => $v) {
            $validated[$k] = $v / 100;
        }

        $plan->update($validated);
        if ($plan->is_popular) {
            Plan::where('id', '!=', $plan->id)->where('is_popular', true)->update(['is_popular' => false]);
        }
        $plan->addons()->sync($addonIds);
        $this->syncPriceTable($plan, $minor);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully.');
    }

    private function rules(): array
    {
        // Pricing fields are stored in MINOR units (cents/paise) per the
        // country-pricing contract. All four (USD/INR × monthly/annual)
        // are required so every plan has explicit per-currency pricing —
        // no implicit FX, no silent USD fallback.
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|integer|min:0',
            'annual_price' => 'required|integer|min:0',
            'monthly_price_secondary' => 'required|integer|min:0',
            'annual_price_secondary' => 'required|integer|min:0',
            'trial_days' => 'required|integer|min:0',
            'grace_days' => 'required|integer|min:0|max:365',
            'refund_window_days' => 'required|integer|min:0|max:365',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'integer|min:0',
            'is_popular' => 'nullable|boolean',
            'features' => 'nullable|array',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'integer|exists:addons,id',
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Plan::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    public function destroy(Plan $plan)
    {
        if ($plan->users()->count() > 0) {
            return back()->with('error', 'Cannot delete a plan that has active users.');
        }

        $plan->delete();
        return redirect()->route('admin.plans.index')->with('success', 'Plan deleted successfully.');
    }

    /**
     * Persist the four required price rows (USD/INR × monthly/annual)
     * into the polymorphic `prices` table — the authoritative source.
     * Values are MINOR units; admin validation guarantees all four are
     * present, so no row is ever deleted here.
     */
    private function syncPriceTable(Plan $plan, array $minor): void
    {
        PricingResolver::upsertFromMinor($plan, 'USD', 'monthly', $minor['monthly_price']);
        PricingResolver::upsertFromMinor($plan, 'USD', 'annual',  $minor['annual_price']);
        PricingResolver::upsertFromMinor($plan, 'INR', 'monthly', $minor['monthly_price_secondary']);
        PricingResolver::upsertFromMinor($plan, 'INR', 'annual',  $minor['annual_price_secondary']);
    }

    private function defaultFeatures(): array
    {
        return [
            'max_links' => 10,
            'max_biolinks' => 1,
            'max_file_size_mb' => 5,
            'storage_limit_mb' => 100,
            'max_projects' => 3,
            'contacts_max' => 100,
            'contacts_google_sync' => false,
            'max_aliases_per_link' => 0,
            'min_alias_length' => 3,
            'max_alias_length' => 50,
            'custom_domains' => false,
            'qr_customization' => false,
            'analytics' => 'basic',
            'pixels' => false,
            'utm_params' => false,
            'link_protection' => false,
            'seo_settings' => false,
            'teams' => false,
            'ecommerce' => false,
            'custom_forms' => false,
            'custom_branding' => false,
            'custom_favicon' => false,
            'custom_code' => false,
        ];
    }
}
