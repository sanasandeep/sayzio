<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent seeder for the 7 default plans and the default addon catalog.
 *
 * The lineup (display name / internal slug):
 *   Starter (free, default) `free` · Creator `creator` · Professional
 *   `professional` · Business `business` · Agency `agency` · Developer
 *   `developer` · Enterprise API `enterprise-api`.
 *
 * The free default plan keeps the historical `free` slug on purpose so the
 * many `isOnFreePlan()` / `slug = 'free'` checks stay correct; its display
 * NAME is "Starter". Default-plan *resolution* must use the `is_default`
 * flag (Plan::defaultPlan()), never a hardcoded slug, so the lineup can be
 * re-shaped from the admin UI.
 *
 * Behaviour:
 * - Matches by slug. Never destroys curator edits: existing rows keep their
 *   name/description/prices/features/sort_order/status — we only fill in
 *   NULL/empty fields (features are overlaid key-by-key) and never touch
 *   user-edited content.
 * - Newly inserted rows get the canonical defaults below.
 * - Safe to run on every deploy / in every fresh environment.
 *
 * One-time legacy handling (archiving the old 5-plan lineup, renaming the
 * colliding `business` slug, and remapping existing subscribers to the
 * closest new plan) lives in the dedicated data migration, NOT here — this
 * seeder only ever converges the new lineup.
 */
class PlansAndAddonsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPlans();
        $this->seedAddons();
    }

    /**
     * Idempotently converge the 7 plan rows and their USD/INR prices. Split
     * out from run() so the one-time lineup data migration can ensure the new
     * plans exist (it needs their ids to remap subscribers) WITHOUT also
     * paying for the slower addon catalog convergence inside the migration —
     * that distinction matters over the distant RDS where the full seeder
     * runs ~2 min. Safe to call repeatedly.
     */
    public function seedPlans(): void
    {
        $defs = $this->planDefinitions();

        // First-class per-plan AI feature limits. Merged here (rather than
        // hardcoded inside each tier's array) so the values live in ONE place
        // shared with the backfill migration, and so both the create path and
        // the overlay-fill path below pick them up. Existing per-tier keys
        // (e.g. ai_widget / ai_voice_assistant) win over these defaults.
        $aiLimits = self::aiFeatureLimits();
        foreach ($defs as &$def) {
            $extra = $aiLimits[$def['slug']] ?? [];
            $def['features'] = array_merge($extra, $def['features']);
        }
        unset($def);

        // Batch-load every existing plan row up front in ONE query, keyed by
        // slug, then diff in memory. This avoids the per-plan SELECT (and the
        // redundant re-SELECT after write) that made a no-op convergence run
        // pay round-trip latency for each plan against the cross-region RDS.
        $existing = Plan::whereIn('slug', array_column($defs, 'slug'))->get()->keyBy('slug');

        $priceItems = [];
        foreach ($defs as $def) {
            $plan = $existing->get($def['slug']);
            if ($plan) {
                // Only fill in fields that are clearly unset on the existing row.
                // We deliberately do NOT overwrite curator edits.
                $patch = [];
                if (empty($plan->description)) $patch['description'] = $def['description'];
                if (empty($plan->features)) {
                    $patch['features'] = $def['features'];
                } else {
                    // Overlay-only: add new feature keys that aren't already
                    // set by the curator. Existing keys are never overwritten.
                    $merged = $plan->features;
                    $changed = false;
                    foreach ($def['features'] as $k => $v) {
                        if (!array_key_exists($k, $merged)) {
                            $merged[$k] = $v;
                            $changed = true;
                        }
                    }
                    if ($changed) $patch['features'] = $merged;
                }
                if ($plan->metadata === null)  $patch['metadata']    = $def['metadata'];
                if ($patch) {
                    $plan->fill($patch)->save();
                }
            } else {
                $plan = Plan::create($def);
            }

            // Reuse the model we already have in memory — no re-SELECT.
            $priceItems[] = ['model' => $plan, 'usdMonthly' => (float) $def['monthly_price'], 'usdAnnual' => (float) $def['annual_price']];
        }

        // Make sure the polymorphic `prices` table has rows for both
        // currencies. Batched so all plans' existing prices load in ONE query.
        $this->seedPricesForModels(Plan::class, $priceItems);
    }

    /**
     * First-class per-plan AI feature limits, keyed by plan slug.
     *
     * Quantity keys (-1 = Unlimited): max_minds, max_personas, max_companions.
     * Availability keys (bool): ask_coach, card_scan, ai_resume_tools.
     * (Site Assistant `ai_widget` and Voice Assistant `ai_voice_assistant`
     * already live in each tier's features array and are not repeated here.)
     *
     * Exposed publicly + statically so the additive backfill migration can
     * reuse the exact same source of truth.
     *
     * @return array<string, array<string, int|bool>>
     */
    public static function aiFeatureLimits(): array
    {
        return [
            'free' => [
                'max_minds'       => 1,
                'max_personas'    => 1,
                'max_companions'  => 1,
                'max_brand_kits'  => 0,
                'ask_coach'       => false,
                'card_scan'       => false,
                'ai_resume_tools' => false,
            ],
            'creator' => [
                'max_minds'       => 3,
                'max_personas'    => 3,
                'max_companions'  => 2,
                'max_brand_kits'  => 1,
                'ask_coach'       => true,
                'card_scan'       => true,
                'ai_resume_tools' => true,
            ],
            'professional' => [
                'max_minds'       => 10,
                'max_personas'    => 10,
                'max_companions'  => 5,
                'max_brand_kits'  => 3,
                'ask_coach'       => true,
                'card_scan'       => true,
                'ai_resume_tools' => true,
            ],
            'business' => [
                'max_minds'       => -1,
                'max_personas'    => -1,
                'max_companions'  => -1,
                'max_brand_kits'  => -1,
                'ask_coach'       => true,
                'card_scan'       => true,
                'ai_resume_tools' => true,
            ],
            'agency' => [
                'max_minds'       => -1,
                'max_personas'    => -1,
                'max_companions'  => -1,
                'max_brand_kits'  => -1,
                'ask_coach'       => true,
                'card_scan'       => true,
                'ai_resume_tools' => true,
            ],
            'developer' => [
                'max_minds'       => 10,
                'max_personas'    => 10,
                'max_companions'  => 5,
                'max_brand_kits'  => 3,
                'ask_coach'       => true,
                'card_scan'       => true,
                'ai_resume_tools' => true,
            ],
            'enterprise-api' => [
                'max_minds'       => -1,
                'max_personas'    => -1,
                'max_companions'  => -1,
                'max_brand_kits'  => -1,
                'ask_coach'       => true,
                'card_scan'       => true,
                'ai_resume_tools' => true,
            ],
        ];
    }

    /**
     * Idempotently converge the default addon catalog, their plan
     * attachments, and their USD/INR prices. Split out from run() so the
     * one-time lineup data migration can skip this slower pass (it only needs
     * the plan rows). Safe to call repeatedly; converges attachments to the
     * current applies_to slug list without ever dropping curator-added links.
     */
    public function seedAddons(): void
    {
        $defs = $this->addonDefinitions();

        // Batch-load every existing addon row up front in ONE query, keyed by
        // slug. Diff in memory rather than a SELECT per addon.
        $existing = Addon::whereIn('slug', array_column($defs, 'slug'))->get()->keyBy('slug');

        // Resolve every distinct applies_to slug to its plan id in ONE query
        // (shared across all addons) instead of a whereIn per addon.
        $appliesSlugs = [];
        foreach ($defs as $def) {
            foreach (($def['applies_to'] ?? []) as $slug) {
                $appliesSlugs[$slug] = true;
            }
        }
        $planIdBySlug = $appliesSlugs
            ? Plan::whereIn('slug', array_keys($appliesSlugs))->pluck('id', 'slug')->all()
            : [];

        // First pass: converge addon rows (create missing / overlay-fill
        // existing) so every addon has an id for the pivot diff below.
        $entries = [];     // slug => ['addon' => Addon, 'applies_to' => string[]]
        $priceItems = [];
        foreach ($defs as $def) {
            $appliesTo = $def['applies_to'] ?? [];
            unset($def['applies_to']);

            $addon = $existing->get($def['slug']);
            if ($addon) {
                // Overlay-only patch: only fill in fields that are clearly
                // unset on the existing row. We never overwrite curator edits
                // (mirrors the plan overlay behavior above).
                $patch = [];
                if (empty($addon->description)) $patch['description'] = $def['description'];
                if (empty($addon->features))    $patch['features']    = $def['features'];
                if ($addon->metadata === null && array_key_exists('metadata', $def)) {
                    $patch['metadata'] = $def['metadata'];
                }
                if ($patch) {
                    $addon->fill($patch)->save();
                }
            } else {
                $addon = Addon::create($def);
            }

            $entries[$def['slug']] = ['addon' => $addon, 'applies_to' => $appliesTo];
            $priceItems[] = ['model' => $addon, 'usdMonthly' => (float) $def['monthly_price'], 'usdAnnual' => (float) $def['annual_price']];
        }

        // Batch-load every existing pivot row for all of these addons in ONE
        // query, then diff in memory. Always converge the default plan
        // attachments so a partial or manually-edited prior seed gets healed on
        // rerun. We only attach pairs that aren't already linked, so any extra
        // plans an admin attached by hand are preserved and a populated pivot
        // table never triggers a duplicate-key crash on re-run.
        $addonIds = [];
        foreach ($entries as $entry) {
            $addonIds[] = $entry['addon']->getKey();
        }
        $existingPivot = []; // addon_id => [plan_id => true]
        if ($addonIds) {
            foreach (DB::table('addon_plan')->whereIn('addon_id', $addonIds)->get(['addon_id', 'plan_id']) as $row) {
                $existingPivot[$row->addon_id][$row->plan_id] = true;
            }
        }

        $toInsert = [];
        foreach ($entries as $entry) {
            $addon = $entry['addon'];
            if (!$entry['applies_to']) {
                continue;
            }
            // Resolve + de-dupe the target plan ids from the shared map.
            $planIds = [];
            foreach ($entry['applies_to'] as $slug) {
                if (isset($planIdBySlug[$slug])) {
                    $planIds[$planIdBySlug[$slug]] = true;
                }
            }
            $have = $existingPivot[$addon->getKey()] ?? [];
            foreach (array_keys($planIds) as $planId) {
                if (!isset($have[$planId])) {
                    $toInsert[] = ['addon_id' => $addon->getKey(), 'plan_id' => $planId];
                }
            }
        }
        if ($toInsert) {
            // Single bulk insert for all missing attachments (mirrors the
            // timestamp-less rows the belongsToMany attach() produced before).
            DB::table('addon_plan')->insert($toInsert);
        }

        // Make sure the polymorphic `prices` table has rows for both
        // currencies. Batched so all addons' existing prices load in ONE query.
        $this->seedPricesForModels(Addon::class, $priceItems);
    }

    /**
     * Idempotently fill USD + INR rows in the polymorphic `prices` table for a
     * batch of models of the SAME class (all Plans or all Addons).
     *
     * INR defaults are derived from USD using a flat multiplier — admins can
     * override per-row via the dual-currency editor in the admin UI. Existing
     * rows (curator edits) are never overwritten.
     *
     * Performance: every existing price row for the whole batch is loaded in
     * ONE query and diffed in memory, instead of four `exists()` round-trips
     * per model. Any missing rows across the whole batch are then written in a
     * SINGLE bulk insert instead of one `upsertFromMinor` round-trip each — so
     * a from-empty seed (where all 84 rows are missing) pays one insert, not
     * 84. Over the cross-region RDS this is what turns first-time provisioning
     * from ~minute-scale into a few seconds. A model that already has all four
     * rows triggers zero writes.
     *
     * @param class-string $class  The priceable class (Plan::class / Addon::class).
     * @param array<int, array{model: \Illuminate\Database\Eloquent\Model, usdMonthly: float, usdAnnual: float}> $items
     */
    private function seedPricesForModels(string $class, array $items): void
    {
        if (!$items) return;
        $inrPerUsd = 83.0;

        // Batch-load existing price rows for every model in one query, then
        // index by "id|currency|cycle" for in-memory membership checks.
        $ids = [];
        foreach ($items as $item) {
            $ids[] = $item['model']->getKey();
        }
        $have = []; // "id|currency|cycle" => true
        foreach (
            Price::where('priceable_type', $class)
                ->whereIn('priceable_id', $ids)
                ->get(['priceable_id', 'currency', 'billing_cycle']) as $price
        ) {
            $have[$price->priceable_id . '|' . $price->currency . '|' . $price->billing_cycle] = true;
        }

        // Accumulate every missing (currency, cycle) row across the whole batch
        // and write them all in ONE bulk insert below. These rows are known to
        // be absent (we just diffed against $have), so a plain insert is safe
        // and idempotent — existing rows / curator edits are never touched.
        $toInsert = [];
        $now = now();

        foreach ($items as $item) {
            $model = $item['model'];
            $usdMonthly = $item['usdMonthly'];
            $usdAnnual = $item['usdAnnual'];

            $rows = [
                ['USD', 'monthly', $usdMonthly],
                ['USD', 'annual',  $usdAnnual],
                ['INR', 'monthly', $usdMonthly > 0 ? round($usdMonthly * $inrPerUsd) : 0],
                ['INR', 'annual',  $usdAnnual  > 0 ? round($usdAnnual  * $inrPerUsd) : 0],
            ];

            foreach ($rows as [$currency, $cycle, $major]) {
                if (!isset($have[$model->getKey() . '|' . $currency . '|' . $cycle])) {
                    $minor = (int) round(((float) $major) * 100);
                    $toInsert[] = [
                        'priceable_type'     => $class,
                        'priceable_id'       => $model->getKey(),
                        'currency'           => $currency,
                        'billing_cycle'      => $cycle,
                        'amount_minor_units' => max(0, $minor),
                        'is_active'          => true,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }
            }

            // Mirror INR amounts into the legacy `*_secondary` decimal columns
            // so the admin edit form's prefill (which still reads those legacy
            // columns) is correct on first open. Strict null check preserves a
            // curator-set 0 INR price on reseed.
            $patch = [];
            if ($model->monthly_price_secondary === null) {
                $patch['monthly_price_secondary'] = $rows[2][2]; // INR monthly
            }
            if ($model->annual_price_secondary === null) {
                $patch['annual_price_secondary'] = $rows[3][2]; // INR annual
            }
            if ($patch) {
                $model->fill($patch)->save();
            }
        }

        // Single bulk insert for every missing row across the whole batch.
        if ($toInsert) {
            DB::table('prices')->insert($toInsert);
        }
    }

    /**
     * The 7-plan lineup. Each definition carries the full feature-key
     * superset (including workspace, AI-suite, integration and alias keys)
     * because these slugs are new — the tier-keyed backfill seed-migrations
     * only recognise the old slugs, so anything omitted here would silently
     * fall back to whatever default a call site happens to pass.
     */
    private function planDefinitions(): array
    {
        return [
            [
                // The free, default plan. Display name is "Starter"; the slug
                // stays `free` so the historical free-plan checks keep working.
                'name' => 'Starter',
                'slug' => 'free',
                'description' => 'Everything you need to launch — free forever, re-confirmed once a year.',
                'monthly_price' => 0,
                'annual_price' => 0,
                'trial_days' => 0,
                'is_default' => true,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 0,
                'metadata' => ['tier' => 'starter', 'free_window_months' => 12],
                'features' => [
                    'max_links' => 10,
                    'max_biolinks' => 1,
                    'max_conversational' => 1,
                    'max_slides' => 1,
                    'max_ai_chat' => 1,
                    'max_restaurant_menu' => 1,
                    'max_service_booking' => 1,
                    'max_reviews' => 1,
                    'max_resume' => 1,
                    'max_file_size_mb' => 5,
                    'storage_limit_mb' => 200,
                    'max_projects' => 1,
                    'contacts_max' => 100,
                    'max_aliases_per_link' => ['default' => 0, 'biolink' => 1, 'short' => 0],
                    // Free/entry plan keeps the LARGEST alias minimum; paid tiers
                    // step down (creator/professional = 3, business+ = 2) as a perk.
                    'min_alias_length' => 4,
                    'max_alias_length' => 50,
                    'max_workspaces' => 1,
                    'max_seats_per_workspace' => 1,
                    'contacts_google_sync' => false,
                    'custom_domains' => false,
                    'max_custom_domains' => 0,
                    'qr_customization' => false,
                    'analytics' => 'basic',
                    'analytics_export' => false,
                    'paid_forms' => false,
                    'form_analytics_advanced' => false,
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
                    'block_types_allowed' => ['heading', 'paragraph', 'avatar', 'link', 'socials', 'spacer', 'divider'],
                    'max_forms' => 1,
                    'buzz_popups' => false,
                    'max_buzz_items' => 0,
                    'max_buzz_impressions' => 0,
                    'splash_pages' => false,
                    'max_splash_pages' => 0,
                    'files' => true,
                    'max_files' => 25,
                    'vaults' => false,
                    'max_vault_items' => 0,
                    'tasks' => true,
                    'max_task_boards' => 1,
                    'leads' => false,
                    'max_leads' => 0,
                    'creator_profile_public' => false,
                    'events' => false,
                    'max_events' => 0,
                    'calendar_sync' => false,
                    'verification_eligible' => false,
                    'link_password' => false,
                    'link_expiry' => false,
                    'link_geo_targeting' => false,
                    'link_device_targeting' => false,
                    'link_deep_link' => false,
                    'link_smart_rules' => false,
                    'link_active_window' => false,
                    'ab_tests' => false,
                    'ab_max_variants' => 0,
                    'api_access' => false,
                    'stats_retention_days' => 365,
                    'api_calls_monthly' => 0,
                    'api_rate_per_min' => 0,
                    'integration_accounts_max' => ['payment' => 1, 'sms' => 1, 'email' => 1],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
            [
                'name' => 'Creator',
                'slug' => 'creator',
                'description' => 'For solo creators ready to grow their audience and brand.',
                'monthly_price' => 9.00,
                'annual_price' => 90.00,
                'trial_days' => 7,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 1,
                'metadata' => ['tier' => 'creator'],
                'features' => [
                    'max_links' => 50,
                    'max_biolinks' => 3,
                    'max_conversational' => 3,
                    'max_slides' => 3,
                    'max_ai_chat' => 3,
                    'max_restaurant_menu' => 3,
                    'max_service_booking' => 3,
                    'max_reviews' => 3,
                    'max_resume' => 3,
                    'max_file_size_mb' => 25,
                    'storage_limit_mb' => 2000,
                    'max_projects' => 3,
                    'contacts_max' => 1000,
                    'max_aliases_per_link' => ['default' => 1, 'biolink' => 3, 'short' => 1],
                    'min_alias_length' => 3,
                    'max_alias_length' => 50,
                    'max_workspaces' => 1,
                    'max_seats_per_workspace' => 2,
                    'contacts_google_sync' => false,
                    'custom_domains' => false,
                    'max_custom_domains' => 0,
                    'qr_customization' => true,
                    'analytics' => 'basic',
                    'analytics_export' => true,
                    'paid_forms' => false,
                    'form_analytics_advanced' => false,
                    'pixels' => false,
                    'utm_params' => true,
                    'link_protection' => false,
                    'seo_settings' => true,
                    'teams' => false,
                    'ecommerce' => false,
                    'custom_forms' => false,
                    'custom_branding' => false,
                    'remove_branding' => false,
                    'custom_favicon' => false,
                    'custom_code' => false,
                    'ai_chatbot' => true,
                    'ai_agent' => false,
                    'ai_widget' => true,
                    'ai_voice_assistant' => false,
                    'block_types_allowed' => ['heading', 'paragraph', 'avatar', 'link', 'socials', 'spacer', 'divider', 'image', 'video', 'card', 'grid', 'grid_auto', 'badge', 'youtube', 'tiktok_video', 'tiktok_profile', 'instagram', 'twitter_profile', 'twitter_tweet', 'twitter_video', 'spotify', 'soundcloud'],
                    'max_forms' => 5,
                    'buzz_popups' => false,
                    'max_buzz_items' => 0,
                    'max_buzz_impressions' => 0,
                    'splash_pages' => true,
                    'max_splash_pages' => 3,
                    'files' => true,
                    'max_files' => 100,
                    'vaults' => false,
                    'max_vault_items' => 0,
                    'tasks' => true,
                    'max_task_boards' => 3,
                    'leads' => true,
                    'max_leads' => 1000,
                    'creator_profile_public' => true,
                    'events' => true,
                    'max_events' => 10,
                    'calendar_sync' => false,
                    'verification_eligible' => false,
                    'link_password' => true,
                    'link_expiry' => true,
                    'link_geo_targeting' => false,
                    'link_device_targeting' => false,
                    'link_deep_link' => false,
                    'link_smart_rules' => false,
                    'link_active_window' => true,
                    'ab_tests' => true,
                    'ab_max_variants' => 2,
                    'api_access' => true,
                    'stats_retention_days' => -1,
                    'api_calls_monthly' => 2000,
                    'api_rate_per_min' => 60,
                    'integration_accounts_max' => ['payment' => 1, 'sms' => 1, 'email' => 1],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Everything you need to grow — custom domains, advanced analytics, AI.',
                'monthly_price' => 19.00,
                'annual_price' => 190.00,
                'trial_days' => 14,
                'is_default' => false,
                'is_popular' => true,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 2,
                'metadata' => ['tier' => 'professional'],
                'features' => [
                    'max_links' => 250,
                    'max_biolinks' => 10,
                    'max_conversational' => 10,
                    'max_slides' => 10,
                    'max_ai_chat' => 10,
                    'max_restaurant_menu' => 10,
                    'max_service_booking' => 10,
                    'max_reviews' => 10,
                    'max_resume' => 10,
                    'max_file_size_mb' => 50,
                    'storage_limit_mb' => 10000,
                    'max_projects' => 10,
                    'contacts_max' => 10000,
                    'max_aliases_per_link' => ['default' => 3, 'biolink' => 5, 'short' => 2],
                    'min_alias_length' => 3,
                    'max_alias_length' => 50,
                    'max_workspaces' => 2,
                    'max_seats_per_workspace' => 3,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'max_custom_domains' => 1,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'analytics_export' => true,
                    'paid_forms' => true,
                    'form_analytics_advanced' => true,
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                    'custom_branding' => false,
                    'remove_branding' => false,
                    'custom_favicon' => false,
                    'custom_code' => false,
                    'ai_chatbot' => true,
                    'ai_agent' => true,
                    'ai_widget' => true,
                    'ai_voice_assistant' => false,
                    'block_types_allowed' => '*',
                    'max_forms' => 25,
                    'buzz_popups' => true,
                    'max_buzz_items' => 10,
                    'max_buzz_impressions' => 10000,
                    'splash_pages' => true,
                    'max_splash_pages' => 25,
                    'files' => true,
                    'max_files' => 1000,
                    'vaults' => true,
                    'max_vault_items' => 100,
                    'tasks' => true,
                    'max_task_boards' => 25,
                    'leads' => true,
                    'max_leads' => 10000,
                    'creator_profile_public' => true,
                    'events' => true,
                    'max_events' => 100,
                    'calendar_sync' => true,
                    'verification_eligible' => true,
                    'link_password' => true,
                    'link_expiry' => true,
                    'link_geo_targeting' => true,
                    'link_device_targeting' => true,
                    'link_deep_link' => true,
                    'link_smart_rules' => false,
                    'link_active_window' => true,
                    'ab_tests' => true,
                    'ab_max_variants' => 3,
                    'api_access' => true,
                    'stats_retention_days' => -1,
                    'api_calls_monthly' => 25000,
                    'api_rate_per_min' => 120,
                    'integration_accounts_max' => ['payment' => 2, 'sms' => 2, 'email' => 2],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For teams scaling fast — unlimited links, team seats, and white-label.',
                'monthly_price' => 49.00,
                'annual_price' => 490.00,
                'trial_days' => 14,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 3,
                'metadata' => ['tier' => 'business'],
                'features' => [
                    'max_links' => -1,
                    'max_biolinks' => -1,
                    'max_conversational' => -1,
                    'max_slides' => -1,
                    'max_ai_chat' => -1,
                    'max_restaurant_menu' => -1,
                    'max_service_booking' => -1,
                    'max_reviews' => -1,
                    'max_resume' => -1,
                    'max_file_size_mb' => 200,
                    'storage_limit_mb' => 50000,
                    'max_projects' => -1,
                    'contacts_max' => -1,
                    'max_aliases_per_link' => ['default' => 5, 'biolink' => 10, 'short' => 3],
                    'min_alias_length' => 2,
                    'max_alias_length' => 60,
                    'max_workspaces' => 5,
                    'max_seats_per_workspace' => 10,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'max_custom_domains' => 3,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'analytics_export' => true,
                    'paid_forms' => true,
                    'form_analytics_advanced' => true,
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                    'custom_branding' => true,
                    'remove_branding' => true,
                    'custom_favicon' => true,
                    'custom_code' => false,
                    'ai_chatbot' => true,
                    'ai_agent' => true,
                    'ai_widget' => true,
                    'ai_voice_assistant' => true,
                    'block_types_allowed' => '*',
                    'max_forms' => -1,
                    'buzz_popups' => true,
                    'max_buzz_items' => 100,
                    'max_buzz_impressions' => 100000,
                    'splash_pages' => true,
                    'max_splash_pages' => -1,
                    'files' => true,
                    'max_files' => -1,
                    'vaults' => true,
                    'max_vault_items' => 1000,
                    'tasks' => true,
                    'max_task_boards' => -1,
                    'leads' => true,
                    'max_leads' => -1,
                    'creator_profile_public' => true,
                    'events' => true,
                    'max_events' => -1,
                    'calendar_sync' => true,
                    'verification_eligible' => true,
                    'link_password' => true,
                    'link_expiry' => true,
                    'link_geo_targeting' => true,
                    'link_device_targeting' => true,
                    'link_deep_link' => true,
                    'link_smart_rules' => true,
                    'link_active_window' => true,
                    'ab_tests' => true,
                    'ab_max_variants' => 4,
                    'api_access' => true,
                    'stats_retention_days' => -1,
                    'api_calls_monthly' => 100000,
                    'api_rate_per_min' => 200,
                    'integration_accounts_max' => ['payment' => 5, 'sms' => 5, 'email' => 5],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
            [
                'name' => 'Agency',
                'slug' => 'agency',
                'description' => 'White-label everything for agencies — unlimited seats and workspaces.',
                'monthly_price' => 99.00,
                'annual_price' => 990.00,
                'trial_days' => 14,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 4,
                'metadata' => ['tier' => 'agency'],
                'features' => [
                    'max_links' => -1,
                    'max_biolinks' => -1,
                    'max_conversational' => -1,
                    'max_slides' => -1,
                    'max_ai_chat' => -1,
                    'max_restaurant_menu' => -1,
                    'max_service_booking' => -1,
                    'max_reviews' => -1,
                    'max_resume' => -1,
                    'max_file_size_mb' => 500,
                    'storage_limit_mb' => -1,
                    'max_projects' => -1,
                    'contacts_max' => -1,
                    'max_aliases_per_link' => ['default' => 10, 'biolink' => 20, 'short' => 5],
                    'min_alias_length' => 2,
                    'max_alias_length' => 60,
                    'max_workspaces' => -1,
                    'max_seats_per_workspace' => -1,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'max_custom_domains' => 10,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'analytics_export' => true,
                    'paid_forms' => true,
                    'form_analytics_advanced' => true,
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                    'custom_branding' => true,
                    'remove_branding' => true,
                    'custom_favicon' => true,
                    'custom_code' => true,
                    'ai_chatbot' => true,
                    'ai_agent' => true,
                    'ai_widget' => true,
                    'ai_voice_assistant' => true,
                    'block_types_allowed' => '*',
                    'max_forms' => -1,
                    'buzz_popups' => true,
                    'max_buzz_items' => -1,
                    'max_buzz_impressions' => -1,
                    'splash_pages' => true,
                    'max_splash_pages' => -1,
                    'files' => true,
                    'max_files' => -1,
                    'vaults' => true,
                    'max_vault_items' => -1,
                    'tasks' => true,
                    'max_task_boards' => -1,
                    'leads' => true,
                    'max_leads' => -1,
                    'creator_profile_public' => true,
                    'events' => true,
                    'max_events' => -1,
                    'calendar_sync' => true,
                    'verification_eligible' => true,
                    'link_password' => true,
                    'link_expiry' => true,
                    'link_geo_targeting' => true,
                    'link_device_targeting' => true,
                    'link_deep_link' => true,
                    'link_smart_rules' => true,
                    'link_active_window' => true,
                    'ab_tests' => true,
                    'ab_max_variants' => -1,
                    'api_access' => true,
                    'stats_retention_days' => -1,
                    'api_calls_monthly' => 250000,
                    'api_rate_per_min' => 300,
                    'integration_accounts_max' => ['payment' => -1, 'sms' => -1, 'email' => -1],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
            [
                'name' => 'Developer',
                'slug' => 'developer',
                'description' => 'Built for builders — high API limits, webhooks, and custom code.',
                'monthly_price' => 29.00,
                'annual_price' => 290.00,
                'trial_days' => 14,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 5,
                'metadata' => ['tier' => 'developer'],
                'features' => [
                    'max_links' => 250,
                    'max_biolinks' => 10,
                    'max_conversational' => 10,
                    'max_slides' => 10,
                    'max_ai_chat' => 10,
                    'max_restaurant_menu' => 10,
                    'max_service_booking' => 10,
                    'max_reviews' => 10,
                    'max_resume' => 10,
                    'max_file_size_mb' => 100,
                    'storage_limit_mb' => 20000,
                    'max_projects' => 25,
                    'contacts_max' => 25000,
                    'max_aliases_per_link' => ['default' => 5, 'biolink' => 10, 'short' => 5],
                    'min_alias_length' => 2,
                    'max_alias_length' => 60,
                    'max_workspaces' => 2,
                    'max_seats_per_workspace' => 3,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'max_custom_domains' => 3,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'analytics_export' => true,
                    'paid_forms' => true,
                    'form_analytics_advanced' => true,
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                    'custom_branding' => false,
                    'remove_branding' => true,
                    'custom_favicon' => true,
                    'custom_code' => true,
                    'ai_chatbot' => true,
                    'ai_agent' => true,
                    'ai_widget' => true,
                    'ai_voice_assistant' => true,
                    'block_types_allowed' => '*',
                    'max_forms' => 50,
                    'buzz_popups' => true,
                    'max_buzz_items' => 50,
                    'max_buzz_impressions' => 50000,
                    'splash_pages' => true,
                    'max_splash_pages' => 50,
                    'files' => true,
                    'max_files' => 5000,
                    'vaults' => true,
                    'max_vault_items' => 500,
                    'tasks' => true,
                    'max_task_boards' => 50,
                    'leads' => true,
                    'max_leads' => 50000,
                    'creator_profile_public' => true,
                    'events' => true,
                    'max_events' => 200,
                    'calendar_sync' => true,
                    'verification_eligible' => true,
                    'link_password' => true,
                    'link_expiry' => true,
                    'link_geo_targeting' => true,
                    'link_device_targeting' => true,
                    'link_deep_link' => true,
                    'link_smart_rules' => true,
                    'link_active_window' => true,
                    'ab_tests' => true,
                    'ab_max_variants' => 5,
                    'api_access' => true,
                    'stats_retention_days' => -1,
                    'api_calls_monthly' => 1000000,
                    'api_rate_per_min' => 600,
                    'integration_accounts_max' => ['payment' => 5, 'sms' => 5, 'email' => 5],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
            [
                'name' => 'Enterprise API',
                'slug' => 'enterprise-api',
                'description' => 'White-glove plan for organizations — unlimited API, SSO, SLAs, dedicated support.',
                'monthly_price' => 299.00,
                'annual_price' => 2990.00,
                'trial_days' => 0,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 6,
                'metadata' => ['tier' => 'enterprise', 'contact_sales' => true],
                'features' => [
                    'max_links' => -1,
                    'max_biolinks' => -1,
                    'max_conversational' => -1,
                    'max_slides' => -1,
                    'max_ai_chat' => -1,
                    'max_restaurant_menu' => -1,
                    'max_service_booking' => -1,
                    'max_reviews' => -1,
                    'max_resume' => -1,
                    'max_file_size_mb' => 500,
                    'storage_limit_mb' => -1,
                    'max_projects' => -1,
                    'contacts_max' => -1,
                    'max_aliases_per_link' => ['default' => -1],
                    'min_alias_length' => 1,
                    'max_alias_length' => 80,
                    'max_workspaces' => -1,
                    'max_seats_per_workspace' => -1,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'max_custom_domains' => -1,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'analytics_export' => true,
                    'paid_forms' => true,
                    'form_analytics_advanced' => true,
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                    'custom_branding' => true,
                    'remove_branding' => true,
                    'custom_favicon' => true,
                    'custom_code' => true,
                    'ai_chatbot' => true,
                    'ai_agent' => true,
                    'ai_widget' => true,
                    'ai_voice_assistant' => true,
                    'block_types_allowed' => '*',
                    'max_forms' => -1,
                    'buzz_popups' => true,
                    'max_buzz_items' => -1,
                    'max_buzz_impressions' => -1,
                    'splash_pages' => true,
                    'max_splash_pages' => -1,
                    'files' => true,
                    'max_files' => -1,
                    'vaults' => true,
                    'max_vault_items' => -1,
                    'tasks' => true,
                    'max_task_boards' => -1,
                    'leads' => true,
                    'max_leads' => -1,
                    'creator_profile_public' => true,
                    'events' => true,
                    'max_events' => -1,
                    'calendar_sync' => true,
                    'verification_eligible' => true,
                    'link_password' => true,
                    'link_expiry' => true,
                    'link_geo_targeting' => true,
                    'link_device_targeting' => true,
                    'link_deep_link' => true,
                    'link_smart_rules' => true,
                    'link_active_window' => true,
                    'ab_tests' => true,
                    'ab_max_variants' => -1,
                    'api_access' => true,
                    'stats_retention_days' => -1,
                    'api_calls_monthly' => -1,
                    'api_rate_per_min' => -1,
                    'integration_accounts_max' => ['payment' => -1, 'sms' => -1, 'email' => -1],
                    'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
                ],
            ],
        ];
    }

    private function addonDefinitions(): array
    {
        // Addon eligibility groups, keyed to the new lineup slugs.
        $paid = ['creator', 'professional', 'business', 'agency', 'developer', 'enterprise-api'];
        $proPlus = ['professional', 'business', 'agency', 'developer', 'enterprise-api'];
        $bizPlus = ['business', 'agency', 'enterprise-api'];

        return [
            [
                'name' => 'Extra Biolink Pages', 'slug' => 'extra-biolinks',
                'description' => 'Add 5 more Link in Bio pages on top of your plan.',
                'type' => 'recurring', 'monthly_price' => 3.00, 'annual_price' => 30.00,
                'features' => ['max_biolinks_extra' => 5],
                'status' => 'active', 'sort_order' => 0, 'applies_to' => $paid,
            ],
            [
                'name' => 'Custom Domain', 'slug' => 'custom-domain',
                'description' => 'Connect one of your own domains for your short links.',
                'type' => 'recurring', 'monthly_price' => 5.00, 'annual_price' => 50.00,
                'features' => ['custom_domains' => true, 'custom_domains_extra' => 1],
                'status' => 'active', 'sort_order' => 1, 'applies_to' => $paid,
            ],
            [
                'name' => 'White-Label / Remove Branding', 'slug' => 'white-label',
                'description' => 'Remove "powered by Sayzio" badges across all public pages.',
                'type' => 'recurring', 'monthly_price' => 7.00, 'annual_price' => 70.00,
                'features' => ['custom_branding' => true, 'remove_branding' => true],
                'status' => 'active', 'sort_order' => 2, 'applies_to' => $proPlus,
            ],
            [
                'name' => 'Advanced Analytics', 'slug' => 'advanced-analytics',
                'description' => 'Geo, device, referrer & cohort analytics for every link.',
                'type' => 'recurring', 'monthly_price' => 4.00, 'annual_price' => 40.00,
                'features' => ['analytics' => 'advanced'],
                'status' => 'active', 'sort_order' => 3, 'applies_to' => $paid,
            ],
            [
                'name' => 'Extra Team Seats (5)', 'slug' => 'team-seats-5',
                'description' => 'Invite 5 more teammates to collaborate on your workspace.',
                'type' => 'recurring', 'monthly_price' => 10.00, 'annual_price' => 100.00,
                'features' => ['teams' => true, 'team_seats_extra' => 5],
                'status' => 'active', 'sort_order' => 4, 'applies_to' => $proPlus,
            ],
            [
                'name' => 'Priority Support', 'slug' => 'priority-support',
                'description' => 'Skip the queue with priority email and chat support.',
                'type' => 'recurring', 'monthly_price' => 9.00, 'annual_price' => 90.00,
                'features' => ['priority_support' => true],
                'status' => 'active', 'sort_order' => 5, 'applies_to' => $paid,
            ],
            [
                'name' => 'Additional Contacts (5,000)', 'slug' => 'contacts-pack-5k',
                'description' => 'Add 5,000 more contacts to your CRM storage.',
                'type' => 'recurring', 'monthly_price' => 6.00, 'annual_price' => 60.00,
                'features' => ['contacts_max_extra' => 5000],
                'status' => 'active', 'sort_order' => 6, 'applies_to' => $paid,
            ],
            [
                'name' => 'Additional Projects (5)', 'slug' => 'projects-pack-5',
                'description' => 'Five more projects/workspaces to organize your links.',
                'type' => 'recurring', 'monthly_price' => 4.00, 'annual_price' => 40.00,
                'features' => ['max_projects_extra' => 5],
                'status' => 'active', 'sort_order' => 7, 'applies_to' => $paid,
            ],
            [
                'name' => 'Premium Templates Pack', 'slug' => 'templates-premium',
                'description' => 'Unlock the full library of premium Link in Bio templates.',
                'type' => 'one_time', 'monthly_price' => 19.00, 'annual_price' => 19.00,
                'features' => ['templates_premium' => true],
                'status' => 'active', 'sort_order' => 8, 'applies_to' => $paid,
            ],
            [
                'name' => 'Removable Sayzio Branding', 'slug' => 'remove-branding',
                'description' => 'Hide the Sayzio wordmark from your public biolink pages.',
                'type' => 'recurring', 'monthly_price' => 3.00, 'annual_price' => 30.00,
                'features' => ['remove_branding' => true],
                'status' => 'active', 'sort_order' => 9, 'applies_to' => $paid,
            ],
            [
                'name' => 'API Access', 'slug' => 'api-access',
                'description' => 'Programmatic access to your links, biolinks and analytics.',
                'type' => 'recurring', 'monthly_price' => 12.00, 'annual_price' => 120.00,
                'features' => ['api_access' => true, 'api_rate_per_min' => 120],
                'status' => 'active', 'sort_order' => 10, 'applies_to' => $proPlus,
            ],
            [
                'name' => 'Scheduled Posts', 'slug' => 'scheduled-posts',
                'description' => 'Queue posts to your followers and have them go live on a schedule.',
                'type' => 'recurring', 'monthly_price' => 5.00, 'annual_price' => 50.00,
                'features' => ['scheduled_posts' => true],
                'status' => 'active', 'sort_order' => 11, 'applies_to' => $paid,
            ],
            [
                'name' => 'Social-Proof Popup', 'slug' => 'social-proof-popup',
                'description' => 'Show recent activity and follower buzz as a live popup on your pages.',
                'type' => 'recurring', 'monthly_price' => 4.00, 'annual_price' => 40.00,
                'features' => ['social_proof_popup' => true],
                'status' => 'active', 'sort_order' => 12, 'applies_to' => $paid,
            ],
            [
                'name' => 'Custom Forms', 'slug' => 'custom-forms-addon',
                'description' => 'Build branded lead-capture forms inside your biolink pages.',
                'type' => 'recurring', 'monthly_price' => 6.00, 'annual_price' => 60.00,
                'features' => ['custom_forms' => true],
                'status' => 'active', 'sort_order' => 14, 'applies_to' => $bizPlus,
            ],
        ];
    }
}
