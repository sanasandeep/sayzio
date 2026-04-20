<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
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
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        if (!isset($validated['features'])) {
            $validated['features'] = $this->defaultFeatures();
        }

        $plan = Plan::create($validated);
        $plan->addons()->sync($addonIds);

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
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        $plan->update($validated);
        $plan->addons()->sync($addonIds);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully.');
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'monthly_price_secondary' => 'nullable|numeric|min:0',
            'annual_price_secondary' => 'nullable|numeric|min:0',
            'trial_days' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'integer|min:0',
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
