<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Services\PricingResolver;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeder for the 5 default plans and 15 default addons.
 *
 * - Matches by slug. Never destroys curator edits: existing rows keep
 *   their name/description/prices/features/sort_order/status — we only
 *   fill in NULL/empty fields and we never touch user-edited content.
 * - Newly inserted rows get the canonical defaults below.
 * - Safe to run on every deploy.
 */
class PlansAndAddonsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->planDefinitions() as $def) {
            $existing = Plan::where('slug', $def['slug'])->first();
            if ($existing) {
                // Only fill in fields that are clearly unset on the existing row.
                // We deliberately do NOT overwrite curator edits.
                $patch = [];
                if (empty($existing->description)) $patch['description'] = $def['description'];
                if (empty($existing->features))    $patch['features']    = $def['features'];
                if ($existing->metadata === null)  $patch['metadata']    = $def['metadata'];
                if ($patch) {
                    $existing->fill($patch)->save();
                }
            } else {
                Plan::create($def);
            }

            // Make sure the polymorphic `prices` table has rows for both
            // currencies. Seeder only fills missing rows so curator edits
            // in /admin/plans are never overwritten.
            $plan = Plan::where('slug', $def['slug'])->first();
            $this->seedPrices($plan, (float) $def['monthly_price'], (float) $def['annual_price']);
        }

        foreach ($this->addonDefinitions() as $def) {
            $appliesTo = $def['applies_to'] ?? [];
            unset($def['applies_to']);

            $existing = Addon::where('slug', $def['slug'])->first();
            if ($existing) {
                // Overlay-only patch: only fill in fields that are clearly
                // unset on the existing row. We never overwrite curator edits
                // (mirrors the plan overlay behavior above).
                $patch = [];
                if (empty($existing->description)) $patch['description'] = $def['description'];
                if (empty($existing->features))    $patch['features']    = $def['features'];
                if ($existing->metadata === null && array_key_exists('metadata', $def)) {
                    $patch['metadata'] = $def['metadata'];
                }
                if ($patch) {
                    $existing->fill($patch)->save();
                }
                $addon = $existing;
            } else {
                $addon = Addon::create($def);
            }

            // Always converge the default plan attachments so a partial or
            // manually-edited prior seed gets healed on rerun. We use
            // syncWithoutDetaching so any extra plans an admin attached
            // by hand are preserved.
            if ($appliesTo) {
                $planIds = Plan::whereIn('slug', $appliesTo)->pluck('id')->all();
                if ($planIds) {
                    $addon->plans()->syncWithoutDetaching($planIds);
                }
            }

            $this->seedPrices($addon, (float) $def['monthly_price'], (float) $def['annual_price']);
        }
    }

    /**
     * Idempotently fill USD + INR rows in the polymorphic `prices` table.
     * INR defaults are derived from USD using a flat multiplier — admins
     * can override per-row via the dual-currency editor in the admin UI.
     * Existing rows (curator edits) are never overwritten.
     */
    private function seedPrices($model, float $usdMonthly, float $usdAnnual): void
    {
        if (!$model) return;
        $inrPerUsd = 83.0;

        $rows = [
            ['USD', 'monthly', $usdMonthly],
            ['USD', 'annual',  $usdAnnual],
            ['INR', 'monthly', $usdMonthly > 0 ? round($usdMonthly * $inrPerUsd) : 0],
            ['INR', 'annual',  $usdAnnual  > 0 ? round($usdAnnual  * $inrPerUsd) : 0],
        ];

        foreach ($rows as [$currency, $cycle, $major]) {
            $exists = Price::where('priceable_type', get_class($model))
                ->where('priceable_id', $model->getKey())
                ->where('currency', $currency)
                ->where('billing_cycle', $cycle)
                ->exists();
            if (!$exists) {
                PricingResolver::upsertFromMajor($model, $currency, $cycle, $major);
            }
        }

        // Mirror INR amounts into the legacy `*_secondary` columns so the
        // admin dual-currency editor prefills correctly. Otherwise a curator
        // opening the edit form would see INR blank, then save would
        // (per upsertFromMajor's "blank means clear") wipe the INR row.
        // Only fill when missing, never overwrite curator edits.
        $patch = [];
        if (empty($model->monthly_price_secondary)) {
            $patch['monthly_price_secondary'] = $rows[2][2]; // INR monthly
        }
        if (empty($model->annual_price_secondary)) {
            $patch['annual_price_secondary'] = $rows[3][2]; // INR annual
        }
        if ($patch) {
            $model->fill($patch)->save();
        }
    }

    private function planDefinitions(): array
    {
        return [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with the basics — perfect for trying 1INME out.',
                'monthly_price' => 0,
                'annual_price' => 0,
                'trial_days' => 0,
                'is_default' => true,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 0,
                'metadata' => ['tier' => 'free'],
                'features' => [
                    'max_links' => 5,
                    'max_biolinks' => 1,
                    'max_file_size_mb' => 5,
                    'storage_limit_mb' => 100,
                    'max_projects' => 1,
                    'contacts_max' => 100,
                    'contacts_google_sync' => false,
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
                ],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For solo creators who need a little more room to grow.',
                'monthly_price' => 4.99,
                'annual_price' => 49.99,
                'trial_days' => 7,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 1,
                'metadata' => ['tier' => 'starter'],
                'features' => [
                    'max_links' => 25,
                    'max_biolinks' => 3,
                    'max_file_size_mb' => 15,
                    'storage_limit_mb' => 500,
                    'max_projects' => 3,
                    'contacts_max' => 500,
                    'contacts_google_sync' => false,
                    'custom_domains' => false,
                    'qr_customization' => true,
                    'analytics' => 'basic',
                    'pixels' => false,
                    'utm_params' => true,
                    'link_protection' => false,
                    'seo_settings' => true,
                    'teams' => false,
                    'ecommerce' => false,
                    'custom_forms' => false,
                ],
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Everything you need to grow — biolinks, custom domains, advanced analytics.',
                'monthly_price' => 9.99,
                'annual_price' => 99.99,
                'trial_days' => 14,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 2,
                'metadata' => ['tier' => 'pro'],
                'features' => [
                    'max_links' => 100,
                    'max_biolinks' => 10,
                    'max_file_size_mb' => 50,
                    'storage_limit_mb' => 5000,
                    'max_projects' => 10,
                    'contacts_max' => 5000,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => false,
                    'custom_forms' => true,
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For teams and businesses with high-volume needs.',
                'monthly_price' => 29.99,
                'annual_price' => 299.99,
                'trial_days' => 14,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 3,
                'metadata' => ['tier' => 'business'],
                'features' => [
                    'max_links' => -1,
                    'max_biolinks' => -1,
                    'max_file_size_mb' => 200,
                    'storage_limit_mb' => 50000,
                    'max_projects' => -1,
                    'contacts_max' => -1,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                ],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'White-glove plan for organizations — SSO, SLAs, dedicated support.',
                'monthly_price' => 99.00,
                'annual_price' => 999.00,
                'trial_days' => 0,
                'is_default' => false,
                'status' => 'active',
                'is_archived' => false,
                'sort_order' => 4,
                'metadata' => ['tier' => 'enterprise', 'contact_sales' => true],
                'features' => [
                    'max_links' => -1,
                    'max_biolinks' => -1,
                    'max_file_size_mb' => 500,
                    'storage_limit_mb' => -1,
                    'max_projects' => -1,
                    'contacts_max' => -1,
                    'contacts_google_sync' => true,
                    'custom_domains' => true,
                    'qr_customization' => true,
                    'analytics' => 'advanced',
                    'pixels' => true,
                    'utm_params' => true,
                    'link_protection' => true,
                    'seo_settings' => true,
                    'teams' => true,
                    'ecommerce' => true,
                    'custom_forms' => true,
                    'custom_branding' => true,
                    'custom_favicon' => true,
                    'custom_code' => true,
                ],
            ],
        ];
    }

    private function addonDefinitions(): array
    {
        $paid = ['starter', 'pro', 'business', 'enterprise'];
        $proPlus = ['pro', 'business', 'enterprise'];
        $bizPlus = ['business', 'enterprise'];

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
                'description' => 'Remove "powered by 1INME" badges across all public pages.',
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
                'name' => 'Removable 1INME Branding', 'slug' => 'remove-branding',
                'description' => 'Hide the 1INME wordmark from your public biolink pages.',
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
                'name' => 'AI Assistant Credits (10k)', 'slug' => 'ai-credits-10k',
                'description' => 'A monthly bucket of 10,000 AI tokens for copy and image generation.',
                'type' => 'metered', 'monthly_price' => 8.00, 'annual_price' => 80.00,
                'features' => ['ai_credits_monthly' => 10000],
                'status' => 'active', 'sort_order' => 13, 'applies_to' => $paid,
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
