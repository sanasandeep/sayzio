<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Common\Support\PlanFormCatalogue;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(private PlanWriter $writer)
    {
    }

    public function index()
    {
        // Active lineup first (the current 7-plan editor surface). Archived
        // legacy plans are kept for historical subscribers but listed in a
        // separate, collapsed section so the main editor shows exactly the
        // active lineup.
        $plans = Plan::withCount('users')->where('is_archived', false)->ordered()->get();
        $archivedPlans = Plan::withCount('users')->where('is_archived', true)->ordered()->get();
        return view('admin.plans.index', compact('plans', 'archivedPlans'));
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

    /**
     * Deep-copy an existing plan as an editable starting point. The copy
     * is defensively created as internal + inactive (and never "popular"
     * or "default") so it can never accidentally go live or appear on a
     * public surface before the admin has reviewed it. The features blob
     * and the polymorphic price rows are both carried over.
     */
    public function duplicate(Plan $plan)
    {
        $clone = $this->writer->duplicate($plan);

        return redirect()->route('admin.plans.edit', $clone)
            ->with('success', 'Plan duplicated. The copy is internal (admin-only) and inactive — review and activate it when ready.');
    }

    public function store(Request $request)
    {
        $this->writer->createFromRequest($request);

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
        $this->writer->updateFromRequest($request, $plan);

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

    /**
     * Sane defaults for a brand-new plan. Mirrors what the seeder writes
     * for the Free tier so admins never get a blank features array.
     */
    private function defaultFeatures(): array
    {
        $modules = [];
        foreach (array_keys(PlanFormCatalogue::modules()) as $mk) {
            $modules[$mk] = true;
        }
        return array_merge($modules, [
            'max_links' => 10,
            'max_biolinks' => 1,
            'max_conversational' => 1,
            'max_slides' => 1,
            'max_ai_chat' => 1,
            'max_restaurant_menu' => 1,
            'max_reviews' => 1,
            'max_file_size_mb' => 5,
            'storage_limit_mb' => 100,
            'max_projects' => 3,
            'contacts_max' => 100,
            'contacts_google_sync' => false,
            'max_aliases_per_link' => 0,
            'min_alias_length' => 3,
            'max_alias_length' => 50,
            'max_workspaces' => 1,
            'max_seats_per_workspace' => 1,
            'custom_domains' => false,
            'max_custom_domains' => 0,
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
            'remove_branding' => false,
            'custom_favicon' => false,
            'custom_code' => false,
            'ai_chatbot' => false,
            'ai_agent' => false,
            'ai_widget' => false,
            'ai_voice_assistant' => false,
            'block_types_allowed' => '*',
            'integration_accounts_max'     => ['payment' => 1, 'sms' => 1, 'email' => 1],
            'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
        ]);
    }
}
