<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PricingPageCache;
use App\Modules\Common\Support\PlanFormCatalogue;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\IntegrationConfigRegistry;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Single source of truth for the plan create / update / duplicate write
 * paths so the web back-office editor and the mobile admin API stay in
 * lockstep (validation rules, the flat-form → features normalization, the
 * polymorphic price-table sync and the deep-copy all live here, never
 * duplicated per surface). Both the web {@see \App\Modules\Admin\Controllers\PlanController}
 * and the mobile {@see \App\Modules\Api\Controllers\AdminPlanController} delegate here.
 */
class PlanWriter
{
    /**
     * Validate the request and create a plan, syncing addons + the
     * authoritative price rows. Honours `is_popular` / `is_internal`.
     */
    public function createFromRequest(Request $request): Plan
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = $this->uniqueSlug($validated['name']);
        $validated['is_popular'] = $request->boolean('is_popular');
        $validated['is_internal'] = $request->boolean('is_internal');
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        $validated['features'] = $this->collectFeatures($request, $validated['features'] ?? []);
        $validated['intro_discount'] = \App\Services\Billing\IntroDiscount::normalize($request->input('intro_discount'));

        // Down-convert MINOR → MAJOR for legacy decimal columns; the
        // polymorphic prices table is then synced from the minor units.
        $minor = $this->minorPrices($validated);
        foreach ($minor as $k => $v) {
            $validated[$k] = $v / 100;
        }

        $plan = Plan::create($validated);
        if ($plan->is_popular) {
            Plan::where('id', '!=', $plan->id)->where('is_popular', true)->update(['is_popular' => false]);
        }
        $plan->addons()->sync($addonIds);
        $this->syncPriceTable($plan, $minor);
        PricingPageCache::flush();

        return $plan;
    }

    /**
     * Validate the request and update an existing plan, preserving feature
     * keys the form doesn't render. Honours `is_popular` / `is_internal`.
     */
    public function updateFromRequest(Request $request, Plan $plan): Plan
    {
        $validated = $request->validate($this->rules());
        $validated['is_popular'] = $request->boolean('is_popular');
        $validated['is_internal'] = $request->boolean('is_internal');
        $addonIds = $validated['addon_ids'] ?? [];
        unset($validated['addon_ids']);

        $validated['features'] = $this->collectFeatures($request, $validated['features'] ?? [], $plan->features ?? []);
        $validated['intro_discount'] = \App\Services\Billing\IntroDiscount::normalize($request->input('intro_discount'));

        $minor = $this->minorPrices($validated);
        foreach ($minor as $k => $v) {
            $validated[$k] = $v / 100;
        }

        $plan->update($validated);
        if ($plan->is_popular) {
            Plan::where('id', '!=', $plan->id)->where('is_popular', true)->update(['is_popular' => false]);
        }
        $plan->addons()->sync($addonIds);
        $this->syncPriceTable($plan, $minor);
        PricingPageCache::flush();

        return $plan;
    }

    /**
     * Deep-copy an existing plan as an editable starting point. The copy
     * is defensively created as internal + inactive (and never "popular"
     * or "default") so it can never accidentally go live or appear on a
     * public surface before the admin has reviewed it. The features blob,
     * addon attachments and the polymorphic price rows are all carried over.
     */
    public function duplicate(Plan $plan): Plan
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

        PricingPageCache::flush();

        return $clone;
    }

    /**
     * @return array{monthly_price:int,annual_price:int,monthly_price_secondary:int,annual_price_secondary:int}
     */
    private function minorPrices(array $validated): array
    {
        return [
            'monthly_price'           => (int) $validated['monthly_price'],
            'annual_price'            => (int) $validated['annual_price'],
            'monthly_price_secondary' => (int) $validated['monthly_price_secondary'],
            'annual_price_secondary'  => (int) $validated['annual_price_secondary'],
        ];
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
    public function collectFeatures(Request $request, array $features, array $existing = []): array
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

        // ---- Per-link-type alias caps ----
        // The global field (`max_aliases_per_link`) was already cast to int by
        // the quantityLimits loop above. Per-type overrides arrive as
        // `max_aliases_per_link_by_type[<type>]`; blank entries mean "inherit
        // the global value". When ANY per-type value is set we persist a map
        // (`['default' => global, '<type>' => int, ...]`); otherwise we keep
        // the legacy scalar so existing readers stay backward compatible.
        $globalAliases = (int) ($out['max_aliases_per_link'] ?? 0);
        $aliasByType = [];
        $knownAliasTypes = PlanFormCatalogue::aliasLinkTypes();
        foreach ((array) ($features['max_aliases_per_link_by_type'] ?? []) as $type => $v) {
            if (!array_key_exists($type, $knownAliasTypes)) continue;
            if ($v === '' || $v === null) continue; // blank = inherit global
            $aliasByType[$type] = (int) $v;
        }
        unset($out['max_aliases_per_link_by_type']);
        $out['max_aliases_per_link'] = $aliasByType
            ? array_merge(['default' => $globalAliases], $aliasByType)
            : $globalAliases;

        // ---- Stats history retention: enforce the 30-day floor ----
        // -1 means "unlimited" (kept forever); any positive value below the
        // floor is raised to 30 so every plan (especially Free) keeps at least
        // a month of analytics history.
        if (array_key_exists('stats_retention_days', $out)) {
            $retention = (int) $out['stats_retention_days'];
            if ($retention !== -1 && $retention < 30) {
                $retention = 30;
            }
            $out['stats_retention_days'] = $retention;
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

        // ---- Included coin grants (non-negative integers; 0 = none) ----
        foreach (PlanFormCatalogue::includedCoinGrants() as $row) {
            if (array_key_exists($row['key'], $features)) {
                $v = (int) $features[$row['key']];
                $out[$row['key']] = max(0, $v);
            }
        }

        // ---- AI coin multipliers (per provider) ----
        // Stored as a float; a blank / non-positive value normalises to 1.0
        // (no change) so the wallet never under-charges. These deliberately
        // sit outside moduleKeys() so the module-off pass below can't wipe
        // them — they govern coin pricing across every AI feature.
        foreach (PlanFormCatalogue::aiCoinMultipliers() as $row) {
            if (!array_key_exists($row['key'], $features)) continue;
            $raw = $features[$row['key']];
            $mult = is_numeric($raw) ? (float) $raw : 0.0;
            $out[$row['key']] = $mult > 0 ? $mult : 1.0;
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
        // inputs in the form still post their values, but we refuse to
        // overwrite anything those modules guard.
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

    /**
     * Validation rules shared by create + update. Identical across the web
     * form and the mobile admin API.
     */
    public function rules(): array
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

            // First-term introductory discount (folded through
            // IntroDiscount::normalize() before persistence).
            'intro_discount'           => 'nullable|array',
            'intro_discount.enabled'   => 'nullable|boolean',
            'intro_discount.type'      => 'nullable|in:percent,fixed',
            'intro_discount.percent'   => 'nullable|integer|min:0|max:100',
            'intro_discount.fixed'     => 'nullable|array',
            'intro_discount.fixed.USD' => 'nullable|integer|min:0',
            'intro_discount.fixed.INR' => 'nullable|integer|min:0',
            'intro_discount.cycles'    => 'nullable|array',
            'intro_discount.cycles.*'  => 'in:monthly,annual',
            'intro_discount.label'     => 'nullable|string|max:120',

            'features' => 'nullable|array',

            // Quantity limits — accept -1 for unlimited.
            'features.max_links'               => 'nullable|integer|min:-1',
            'features.max_biolinks'            => 'nullable|integer|min:-1',
            'features.max_conversational'      => 'nullable|integer|min:-1',
            'features.max_slides'              => 'nullable|integer|min:-1',
            'features.max_ai_chat'             => 'nullable|integer|min:-1',
            'features.max_restaurant_menu'     => 'nullable|integer|min:-1',
            'features.max_service_booking'     => 'nullable|integer|min:-1',
            'features.max_reviews'             => 'nullable|integer|min:-1',
            'features.max_resume'              => 'nullable|integer|min:-1',
            'features.max_projects'            => 'nullable|integer|min:-1',
            'features.max_file_size_mb'        => 'nullable|integer|min:-1',
            'features.storage_limit_mb'        => 'nullable|integer|min:-1',
            'features.contacts_max'            => 'nullable|integer|min:-1',
            'features.max_custom_domains'      => 'nullable|integer|min:-1',
            'features.max_aliases_per_link'    => 'nullable|integer|min:-1',
            'features.max_aliases_per_link_by_type'   => 'nullable|array',
            'features.max_aliases_per_link_by_type.*' => 'nullable|integer|min:-1',
            'features.min_alias_length'        => 'nullable|integer|min:-1|max:191',
            'features.max_alias_length'        => 'nullable|integer|min:-1|max:191',
            'features.max_workspaces'          => 'nullable|integer|min:-1',
            'features.max_seats_per_workspace' => 'nullable|integer|min:-1',
            'features.max_forms'               => 'nullable|integer|min:-1',
            'features.max_buzz_items'          => 'nullable|integer|min:-1',
            'features.max_buzz_impressions'    => 'nullable|integer|min:-1',
            'features.max_splash_pages'        => 'nullable|integer|min:-1',
            'features.max_files'               => 'nullable|integer|min:-1',
            'features.max_vault_items'         => 'nullable|integer|min:-1',
            'features.max_task_boards'         => 'nullable|integer|min:-1',
            'features.max_leads'               => 'nullable|integer|min:-1',
            'features.max_events'              => 'nullable|integer|min:-1',
            'features.api_calls_monthly'       => 'nullable|integer|min:-1',
            'features.api_rate_per_min'        => 'nullable|integer|min:-1',
            'features.stats_retention_days'    => 'nullable|integer|min:-1',
            'features.signup_bonus_days'       => 'nullable|integer|min:0|max:3650',
            'features.referrer_free_days'      => 'nullable|integer|min:0|max:3650',
            'features.referred_free_days'      => 'nullable|integer|min:0|max:3650',

            // AI coin pricing multipliers (per provider) — float, blank/0 = 1×.
            'features.ai_openai_coin_multiplier'     => 'nullable|numeric|min:0',
            'features.ai_elevenlabs_coin_multiplier' => 'nullable|numeric|min:0',

            // Included coin grants — non-negative integer coin counts per billing cycle.
            'features.included_coins_monthly' => 'nullable|integer|min:0',
            'features.included_coins_yearly'  => 'nullable|integer|min:0',

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

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        return \App\Support\UniqueSuffix::resolve(Plan::query(), $base);
    }

    /**
     * Persist the four required price rows (USD/INR × monthly/annual)
     * into the polymorphic `prices` table — the authoritative source.
     */
    public function syncPriceTable(Plan $plan, array $minor): void
    {
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', $minor['monthly_price']],
            ['USD', 'annual',  $minor['annual_price']],
            ['INR', 'monthly', $minor['monthly_price_secondary']],
            ['INR', 'annual',  $minor['annual_price_secondary']],
        ]);
    }
}
