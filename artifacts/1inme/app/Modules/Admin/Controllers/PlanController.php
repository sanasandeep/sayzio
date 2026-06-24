<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PlanFormCatalogue;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\IntegrationConfigRegistry;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
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
        $copyName = $plan->name . ' (Copy)';

        $clone = $plan->replicate([
            'slug', 'is_default', 'is_popular', 'is_archived',
            'created_at', 'updated_at',
        ]);
        $clone->name = $copyName;
        $clone->slug = $this->uniqueSlug($copyName);
        $clone->is_default = false;
        $clone->is_popular = false;
        $clone->is_archived = false;
        $clone->is_internal = true;
        $clone->status = 'inactive';
        $clone->save();

        // Carry over addon attachments and the authoritative price rows.
        $clone->addons()->sync($plan->addons()->pluck('addons.id')->all());
        foreach ($plan->prices as $price) {
            $newPrice = $price->replicate(['created_at', 'updated_at']);
            $newPrice->priceable_id = $clone->id;
            $newPrice->save();
        }

        return redirect()->route('admin.plans.edit', $clone)
            ->with('success', 'Plan duplicated. The copy is internal (admin-only) and inactive — review and activate it when ready.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = $this->uniqueSlug($validated['name']);
        $validated['is_popular'] = $request->boolean('is_popular');
        $validated['is_internal'] = $request->boolean('is_internal');
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        $validated['features'] = $this->collectFeatures($request, $validated['features'] ?? []);

        // Down-convert MINOR → MAJOR for legacy decimal columns; the
        // polymorphic prices table is then synced from the minor units.
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
        $validated['is_internal'] = $request->boolean('is_internal');
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        $validated['features'] = $this->collectFeatures($request, $validated['features'] ?? [], $plan->features ?? []);

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

    /**
     * Merge the form's flat `features[...]` array with the top-level
     * `block_types_allowed`, `block_mode`, `provider_mode` and
     * `integration_providers_allowed` inputs into one normalized array
     * ready for persistence. Unknown keys already inside `features[...]`
     * are preserved (so `upload_limits`, `referrer_free_days`, etc. carry
     * through untouched), and missing module / boolean keys are coerced
     * to false. Quantity keys honour `-1 = unlimited`.
     */
    private function collectFeatures(Request $request, array $features, array $existing = []): array
    {
        // Start from the existing features so any keys the form doesn't render
        // (e.g. legacy `referrer_free_days`, `upload_limits`) are preserved.
        $out = array_merge($existing, $features);

        // ---- Modules: ensure every module key is present as a boolean ----
        foreach (array_keys(PlanFormCatalogue::modules()) as $moduleKey) {
            $out[$moduleKey] = !empty($features[$moduleKey]);
        }

        // ---- Quantity limits: cast to int (allows -1) ----
        foreach (PlanFormCatalogue::quantityLimits() as $q) {
            if (array_key_exists($q['key'], $features)) {
                $out[$q['key']] = (int) $features[$q['key']];
            }
        }
        foreach (['max_workspaces', 'max_seats_per_workspace'] as $k) {
            if (array_key_exists($k, $features)) {
                $out[$k] = (int) $features[$k];
            }
        }

        // ---- Boolean & select feature flags ----
        foreach (PlanFormCatalogue::featureFlags() as $flag) {
            if ($flag['type'] === 'bool') {
                $out[$flag['key']] = !empty($features[$flag['key']]);
            } elseif ($flag['type'] === 'select') {
                $out[$flag['key']] = $features[$flag['key']] ?? ($flag['default'] ?? null);
            }
        }
        // Teams toggle (lives in the Team section, not in featureFlags).
        $out['teams'] = !empty($features['teams']);

        // ---- AI suite booleans ----
        foreach (PlanFormCatalogue::aiSuite() as $row) {
            $out[$row['key']] = !empty($features[$row['key']]);
        }

        // ---- Block allowlist: '*' or array of known slugs ----
        $blockMode = $request->input('block_mode', 'all');
        if ($blockMode === 'all') {
            $out['block_types_allowed'] = '*';
        } else {
            $picked = (array) $request->input('block_types_allowed', []);
            $known  = array_keys(BiolinkBlock::TYPES);
            $out['block_types_allowed'] = array_values(array_intersect($picked, $known));
        }

        // ---- Integration accounts: cap (per kind) + provider allowlist ----
        $caps    = (array) ($features['integration_accounts_max'] ?? []);
        $allowed = (array) $request->input('integration_providers_allowed', []);
        $modes   = (array) $request->input('provider_mode', []);
        $kinds   = array_keys(IntegrationConfigRegistry::kinds());
        $capsOut = [];
        $allowOut = [];
        foreach ($kinds as $kind) {
            $capsOut[$kind] = isset($caps[$kind]) ? (int) $caps[$kind] : 1;
            if (($modes[$kind] ?? 'all') === 'all') {
                $allowOut[$kind] = '*';
            } else {
                $known = array_keys(IntegrationConfigRegistry::providers($kind));
                $allowOut[$kind] = array_values(array_intersect((array) ($allowed[$kind] ?? []), $known));
            }
        }
        $out['integration_accounts_max']     = $capsOut;
        $out['integration_providers_allowed'] = $allowOut;

        // ---- Module-off: ignore sub-controls on save ----
        // For any module that is currently OFF, restore each gated sub-key
        // back to its existing value (or, on a brand-new plan with no
        // existing features, drop it entirely). The visually-dimmed
        // inputs in the form still post their values, but the controller
        // refuses to overwrite anything those modules guard.
        foreach (PlanFormCatalogue::moduleKeys() as $moduleKey => $gatedKeys) {
            if (!empty($out[$moduleKey])) continue;
            foreach ($gatedKeys as $gk) {
                if (array_key_exists($gk, $existing)) {
                    $out[$gk] = $existing[$gk];
                } else {
                    unset($out[$gk]);
                }
            }
        }

        return $out;
    }

    private function rules(): array
    {
        $blockSlugs    = array_keys(BiolinkBlock::TYPES);
        $providerRules = [];
        foreach (array_keys(IntegrationConfigRegistry::kinds()) as $kind) {
            $known = array_keys(IntegrationConfigRegistry::providers($kind));
            $providerRules["integration_providers_allowed.$kind"]   = 'nullable|array';
            $providerRules["integration_providers_allowed.$kind.*"] = ['string', \Illuminate\Validation\Rule::in($known)];
        }
        return array_merge($providerRules, [
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
            'is_internal' => 'nullable|boolean',
            'features' => 'nullable|array',

            // Quantity limits — accept -1 for unlimited.
            'features.max_links'               => 'nullable|integer|min:-1',
            'features.max_biolinks'            => 'nullable|integer|min:-1',
            'features.max_conversational'      => 'nullable|integer|min:-1',
            'features.max_slides'              => 'nullable|integer|min:-1',
            'features.max_ai_chat'             => 'nullable|integer|min:-1',
            'features.max_restaurant_menu'     => 'nullable|integer|min:-1',
            'features.max_reviews'             => 'nullable|integer|min:-1',
            'features.max_resume'              => 'nullable|integer|min:-1',
            'features.max_projects'            => 'nullable|integer|min:-1',
            'features.max_file_size_mb'        => 'nullable|integer|min:-1',
            'features.storage_limit_mb'        => 'nullable|integer|min:-1',
            'features.contacts_max'            => 'nullable|integer|min:-1',
            'features.max_aliases_per_link'    => 'nullable|integer|min:-1',
            'features.min_alias_length'        => 'nullable|integer|min:-1|max:191',
            'features.max_alias_length'        => 'nullable|integer|min:-1|max:191',
            'features.max_workspaces'          => 'nullable|integer|min:-1',
            'features.max_seats_per_workspace' => 'nullable|integer|min:-1',
            'features.max_forms'               => 'nullable|integer|min:-1',
            'features.max_buzz_items'          => 'nullable|integer|min:-1',
            'features.max_splash_pages'        => 'nullable|integer|min:-1',
            'features.max_files'               => 'nullable|integer|min:-1',
            'features.max_vault_items'         => 'nullable|integer|min:-1',
            'features.max_task_boards'         => 'nullable|integer|min:-1',
            'features.max_leads'               => 'nullable|integer|min:-1',
            'features.max_events'              => 'nullable|integer|min:-1',
            'features.api_calls_monthly'       => 'nullable|integer|min:-1',
            'features.api_rate_per_min'        => 'nullable|integer|min:-1',
            'features.signup_bonus_days'       => 'nullable|integer|min:0|max:3650',
            'features.referrer_free_days'      => 'nullable|integer|min:0|max:3650',
            'features.referred_free_days'      => 'nullable|integer|min:0|max:3650',

            // Analytics select.
            'features.analytics' => 'nullable|in:basic,advanced',

            // Integration caps (per-kind nested ints).
            'features.integration_accounts_max'         => 'nullable|array',
            'features.integration_accounts_max.payment' => 'nullable|integer|min:-1',
            'features.integration_accounts_max.sms'     => 'nullable|integer|min:-1',
            'features.integration_accounts_max.email'   => 'nullable|integer|min:-1',

            // Block allowlist + provider allowlist (top-level, folded into features by collectFeatures()).
            'block_mode'                  => 'nullable|in:all,pick',
            'block_types_allowed'         => 'nullable|array',
            'block_types_allowed.*'       => ['string', \Illuminate\Validation\Rule::in($blockSlugs)],
            'provider_mode'               => 'nullable|array',
            'provider_mode.*'             => 'in:all,pick',
            'integration_providers_allowed'   => 'nullable|array',

            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'integer|exists:addons,id',
        ]);
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
     */
    private function syncPriceTable(Plan $plan, array $minor): void
    {
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', $minor['monthly_price']],
            ['USD', 'annual',  $minor['annual_price']],
            ['INR', 'monthly', $minor['monthly_price_secondary']],
            ['INR', 'annual',  $minor['annual_price_secondary']],
        ]);
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
