<?php

namespace App\Services;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;

/**
 * Task #6737 — the defaults catalog behind the Marketing Plan Calculator.
 *
 * Encodes the "Sayzio-Powered Digital Marketing Plan — 12 Month"
 * spreadsheet's starting benchmarks: the 16-channel assumption table
 * (including the fixed-cost / organic Sayzio row), monthly seasonality
 * weights, the toolset-effectiveness uplifts, and the "standalone tools
 * you'd need instead" cost table for the ROI tab.
 *
 * Sayzio plan pricing is NOT hardcoded here — it is resolved live from
 * the plans/prices tables via {@see planOptions()}.
 */
class MarketingPlanDefaults
{
    /**
     * Channel benchmark defaults straight from the spreadsheet's
     * "Channel Assumptions" table. All money is in INR (the model's base
     * currency); `alloc` is a fraction of the annual ad budget (the fixed
     * Sayzio row has none — its spend is the subscription + AI credits).
     *
     * @var array<int,array<string,mixed>>
     */
    public const CHANNELS = [
        ['key' => 'sayzio',     'name' => 'Sayzio (Link-in-Bio, QR & Short Links)', 'fixed' => true,  'alloc' => null,  'cpv' => null, 'vl' => 5.0,  'lc' => 15.0, 'acv' => 120000, 'notes' => 'Fixed monthly subscription — driven by owned/organic bio-page, QR & short-link traffic, not ad spend'],
        ['key' => 'instagram',  'name' => 'Instagram Ads',                          'fixed' => false, 'alloc' => 5.5,  'cpv' => 800,  'vl' => 9.0,  'lc' => 22.0, 'acv' => 120000, 'notes' => 'Reels/Stories/Feed ads — younger demographic, high engagement'],
        ['key' => 'facebook',   'name' => 'Facebook Ads',                           'fixed' => false, 'alloc' => 4.5,  'cpv' => 900,  'vl' => 8.0,  'lc' => 24.0, 'acv' => 120000, 'notes' => 'Broadest reach across age groups, strong retargeting'],
        ['key' => 'whatsapp',   'name' => 'WhatsApp Business',                      'fixed' => false, 'alloc' => 2.0,  'cpv' => 500,  'vl' => 12.0, 'lc' => 30.0, 'acv' => 120000, 'notes' => 'Click-to-WhatsApp ads & broadcast — high-intent, personal channel'],
        ['key' => 'twitter',    'name' => 'Twitter/X Ads',                          'fixed' => false, 'alloc' => 1.5,  'cpv' => 1000, 'vl' => 6.0,  'lc' => 18.0, 'acv' => 120000, 'notes' => 'Lower-funnel awareness, real-time engagement'],
        ['key' => 'linkedin',   'name' => 'LinkedIn Ads',                           'fixed' => false, 'alloc' => 2.5,  'cpv' => 2000, 'vl' => 10.0, 'lc' => 28.0, 'acv' => 120000, 'notes' => 'Higher cost per visitor but strong B2B/professional lead quality'],
        ['key' => 'gdisplay',   'name' => 'Google Display & Discovery',             'fixed' => false, 'alloc' => 2.5,  'cpv' => 700,  'vl' => 7.0,  'lc' => 20.0, 'acv' => 120000, 'notes' => "Google's social-style placements — Display Network & Discovery feed (separate from Paid Search below)"],
        ['key' => 'youtube',    'name' => 'YouTube Ads',                            'fixed' => false, 'alloc' => 2.0,  'cpv' => 1100, 'vl' => 6.0,  'lc' => 19.0, 'acv' => 120000, 'notes' => 'Video pre-roll/in-feed — strong for brand storytelling'],
        ['key' => 'amazon',     'name' => 'Amazon Ads',                             'fixed' => false, 'alloc' => 1.0,  'cpv' => 1200, 'vl' => 15.0, 'lc' => 35.0, 'acv' => 120000, 'notes' => 'Sponsored/retail media — shoppers already in purchase mode'],
        ['key' => 'othersoc',   'name' => 'Other Social/Display',                   'fixed' => false, 'alloc' => 0.5,  'cpv' => 900,  'vl' => 7.0,  'lc' => 20.0, 'acv' => 120000, 'notes' => 'Pinterest, Snapchat, Reddit & other smaller paid placements'],
        ['key' => 'search',     'name' => 'Paid Search (Google Ads)',               'fixed' => false, 'alloc' => 17.0, 'cpv' => 1440, 'vl' => 12.0, 'lc' => 30.0, 'acv' => 120000, 'notes' => 'High-intent search traffic, best close rate'],
        ['key' => 'seo',        'name' => 'SEO & Content Marketing',                'fixed' => false, 'alloc' => 17.0, 'cpv' => 160,  'vl' => 4.0,  'lc' => 20.0, 'acv' => 120000, 'notes' => 'Blog, on-page SEO, backlinks — slower ramp, compounding'],
        ['key' => 'email',      'name' => 'Email Marketing',                        'fixed' => false, 'alloc' => 11.0, 'cpv' => 40,   'vl' => 5.0,  'lc' => 12.5, 'acv' => 120000, 'notes' => 'Owned list — newsletters, lifecycle & promo sends'],
        ['key' => 'influencer', 'name' => 'Influencer & Affiliate Marketing',       'fixed' => false, 'alloc' => 13.0, 'cpv' => 800,  'vl' => 7.0,  'lc' => 20.0, 'acv' => 120000, 'notes' => 'Creator partnerships & affiliate commissions'],
        ['key' => 'events',     'name' => 'Events & Webinars',                      'fixed' => false, 'alloc' => 11.0, 'cpv' => 2400, 'vl' => 20.0, 'lc' => 33.0, 'acv' => 120000, 'notes' => 'Highest cost per visitor, but highest intent & close rate'],
        ['key' => 'pr',         'name' => 'PR & Media Outreach',                    'fixed' => false, 'alloc' => 9.0,  'cpv' => 320,  'vl' => 6.0,  'lc' => 19.0, 'acv' => 120000, 'notes' => 'Earned media, press placements'],
    ];

    /**
     * "If you didn't use Sayzio — what you'd need instead" table for the
     * ROI tab (estimated standalone monthly tool costs, INR).
     *
     * @var array<int,array<string,mixed>>
     */
    public const TOOLS = [
        ['feature' => 'Link-in-Bio Page',              'example' => 'Linktree Pro / Beacons',      'cost' => 1600, 'notes' => "Sayzio's bio-link mini-site replaces this"],
        ['feature' => 'QR Code Generator',             'example' => 'Dynamic QR code tools',       'cost' => 1200, 'notes' => 'Trackable, brandable QR codes'],
        ['feature' => 'Digital Business Card',         'example' => 'Blinq / CardX',               'cost' => 800,  'notes' => 'Shareable digital contact card'],
        ['feature' => 'CRM & Contacts',                'example' => 'Zoho CRM / HubSpot Starter',  'cost' => 4000, 'notes' => 'Central contact database & pipeline'],
        ['feature' => 'Dialer / Click-to-Call',        'example' => 'JustCall / Ozonetel',         'cost' => 3500, 'notes' => 'Click-to-call & call logging'],
        ['feature' => 'Lead Capture Forms',            'example' => 'Typeform / Google Forms',     'cost' => 1500, 'notes' => 'Embeddable lead-capture forms'],
        ['feature' => 'Live Chat / AI Site Assistant', 'example' => 'Tidio / Intercom',            'cost' => 5000, 'notes' => 'On-site chat widget & AI assistant'],
        ['feature' => 'File-Share Links',              'example' => 'WeTransfer Pro / Dropbox',    'cost' => 800,  'notes' => 'Branded, trackable file-share links'],
        ['feature' => 'Event Page / RSVP',             'example' => 'Eventbrite',                  'cost' => 2000, 'notes' => 'Event landing page & registration'],
        ['feature' => 'Review / Testimonial Page',     'example' => 'Trustpilot Business',         'cost' => 3000, 'notes' => 'Collect & display customer reviews'],
        ['feature' => 'Restaurant Menu / Website Page','example' => 'Wix / Bites',                 'cost' => 2500, 'notes' => 'Menu builder / simple website page'],
    ];

    /**
     * Task #6769 — Conservative / Aggressive scenario multipliers, stored as
     * percentages of the saved ("Expected") base. 100 = unchanged. The
     * Expected scenario has no multipliers — it IS the saved plan.
     *
     * @var array<string,array<string,float>>
     */
    public const SCENARIO_DEFAULTS = [
        'conservative' => ['cpv' => 120.0, 'vl' => 80.0,  'lc' => 80.0,  'budget' => 80.0],
        'aggressive'   => ['cpv' => 80.0,  'vl' => 120.0, 'lc' => 120.0, 'budget' => 120.0],
    ];

    /**
     * A fresh plan's full assumption payload (the spreadsheet's defaults).
     * The Sayzio plan selection defaults to the user's actual current plan,
     * priced live from the plans/prices tables.
     *
     * @return array<string,mixed>
     */
    public static function defaults(?User $user = null): array
    {
        $planSlug = self::preselectPlanSlug($user);

        return [
            'company'          => '',
            'industry_preset'  => MarketingPlanIndustryPresets::GENERIC, // Task #6767
            'annual_budget'    => 180000,      // INR, paid channels only
            'display_currency' => 'INR',
            'usd_inr_rate'     => 83.0,        // editable USD→INR display rate
            'weights'          => array_fill(0, 12, 1.0),
            'plan_slug'        => $planSlug,
            'ai_credits'       => 2000,        // ₹/month (~$20 under the USD toggle) — a realistic starting point, fully editable
            'organic_visitors' => 8000,        // est. monthly organic (bio page, QR & short links)
            'uplifts'          => ['apply' => true, 'chat' => 8.0, 'crm' => 15.0],
            // Task #6768 — finance assumptions behind CAC / ROAS / LTV:CAC,
            // break-even & payback metrics.
            'gross_margin'     => 60.0,        // % of revenue kept as gross profit
            'ltv_multiplier'   => 1.5,         // customer lifetime / repeat-purchase multiplier on customer value
            'scenarios'        => self::SCENARIO_DEFAULTS, // Task #6769
            'channels'         => self::CHANNELS,
            'tools'            => self::TOOLS,
            'hours_per_tool'   => 1.5,
            'time_value'       => 1000,        // ₹/hour
        ];
    }

    /**
     * The plan slug a NEW marketing plan preselects. Only ever resolves to
     * a public, active, non-archived plan — the selector lists exactly
     * those, so an internal/hidden/archived "current plan" (or default
     * plan) must gracefully fall back instead of preselecting a ghost
     * option (Task #6765).
     */
    public static function preselectPlanSlug(?User $user = null): ?string
    {
        try {
            $isSelectable = fn (?Plan $plan): bool => $plan !== null
                && !$plan->is_internal
                && !$plan->is_archived
                && $plan->status === 'active';

            $own = $user?->plan;
            if ($isSelectable($own)) {
                return (string) $own->slug;
            }

            $default = Plan::defaultPlan();
            if ($isSelectable($default)) {
                return (string) $default->slug;
            }

            $slug = Plan::query()->active()->public()->ordered()->value('slug');
            return $slug !== null ? (string) $slug : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Live plan options for the Sayzio plan selector: every self-serve
     * plan with its real monthly INR and USD price from the prices table.
     *
     * @return array<int,array{slug:string,name:string,inr:float,usd:float}>
     */
    public static function planOptions(): array
    {
        try {
            return Plan::query()->active()->public()->ordered()->get()
                ->map(function (Plan $plan) {
                    $inr = PricingResolver::priceForCurrency($plan, 'INR', 'monthly');
                    $usd = PricingResolver::priceForCurrency($plan, 'USD', 'monthly');
                    return [
                        'slug' => (string) $plan->slug,
                        'name' => (string) $plan->name,
                        'inr'  => round(((int) ($inr['amount_minor'] ?? 0)) / 100, 2),
                        'usd'  => round(((int) ($usd['amount_minor'] ?? 0)) / 100, 2),
                    ];
                })->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
