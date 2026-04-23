<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        // Landing-page pricing is intentionally just two cards now:
        // the always-free entry plan and the curator-flagged "popular"
        // plan. The full plan grid lives at /pricing — keeping the
        // landing page focused removes choice paralysis above the fold.
        $allPlans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()
            ->get();

        $isFree = fn ($p) => (int) PricingResolver::priceFor($p, $user, 'monthly')['amount_minor'] === 0;
        $freePlan = $allPlans->first($isFree);
        $popularPlan = $allPlans->firstWhere('is_popular', true)
            ?? $allPlans->first(fn ($p) => !$isFree($p)); // Fallback if none flagged.

        $plans = collect([$freePlan, $popularPlan])->filter()->unique('id')->values()
            ->map(function ($p) use ($user, $billing, $hasAddress, $currency) {
                $monthly = PricingResolver::priceFor($p, $user, 'monthly');
                $annual = PricingResolver::priceFor($p, $user, 'annual');
                // Annual teaser payload: surfaced whenever both monthly AND
                // annual rows exist for the resolved currency. The savings
                // percentage is only meaningful when the annual price is
                // genuinely cheaper than 12× monthly — otherwise we still
                // show "Billed annually at X/yr" so visitors comparing on
                // yearly cost always see the number, with `percent` = 0 so
                // the view can hide the discount label.
                // Stored in MINOR units so we don't reintroduce float math.
                $annualTeaser = null;
                $monthlyMinor = (int) $monthly['amount_minor'];
                $annualMinor = (int) $annual['amount_minor'];
                if ($monthlyMinor > 0 && $annualMinor > 0) {
                    $fullYearMinor = $monthlyMinor * 12;
                    $savedMinor = max(0, $fullYearMinor - $annualMinor);
                    $annualTeaser = [
                        'percent'         => $fullYearMinor > 0
                            ? (int) round(($savedMinor / $fullYearMinor) * 100)
                            : 0,
                        'annual'          => $annual,
                        'saved_formatted' => PricingResolver::money($savedMinor, $annual['currency']),
                    ];
                }
                $tax = null;
                if ($hasAddress && (int) $monthly['amount_minor'] > 0) {
                    $tax = TaxCalculator::calculate(
                        [['label' => 'Plan', 'amount_minor' => (int) $monthly['amount_minor']]],
                        [
                            'country'     => $billing->country,
                            'region'      => $billing->region,
                            'tax_id'      => $billing->tax_id,
                            'tax_id_kind' => $billing->tax_id_kind,
                        ],
                        $currency,
                    );
                }
                return [
                    'name'        => $p->name,
                    'description' => $p->description,
                    'features'    => $p->features ?? [],
                    'is_free'     => $monthly['amount_minor'] <= 0,
                    'is_popular'  => (bool) $p->is_popular,
                    'monthly'     => $monthly,
                    'annual_teaser' => $annualTeaser,
                    'tax'         => $tax,
                ];
            });

        // Featured-post carousel for the landing page. The marketing
        // seeder flags the top 3 posts with `is_featured_home` (across
        // both `hero` and `carousel` slots) — we surface all of them in
        // a single small carousel/grid below the fold so new content
        // gets immediate visibility from the homepage.
        $featuredBlogPosts = collect();
        try {
            $featuredBlogPosts = \App\Modules\Common\Models\BlogPost::published()
                ->featured()
                ->with('category', 'author')
                ->orderByRaw("CASE WHEN featured_slot = 'hero' THEN 0 WHEN featured_slot = 'carousel' THEN 1 ELSE 2 END")
                ->orderByDesc('published_at')
                ->take(3)
                ->get();
        } catch (\Throwable $e) {
            // Blogs migration not run yet — silently skip the carousel.
        }

        return view('home', compact('plans', 'currency', 'currencySource', 'user', 'hasAddress', 'featuredBlogPosts'));
    }
}
