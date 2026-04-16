<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
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
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if (!isset($validated['features'])) {
            $validated['features'] = $this->defaultFeatures();
        }

        Plan::create($validated);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'annual_price' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:0',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|array',
        ]);

        $plan->update($validated);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully.');
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
            'max_aliases_per_link' => 0,
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
