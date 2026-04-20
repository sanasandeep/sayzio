<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // Resolve currency for the (possibly anonymous) visitor so the
        // pricing section renders in the right currency on first paint.
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);

        // Show only the 3 lowest-tier active plans on the marketing page.
        $plans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()
            ->take(3)
            ->get()
            ->map(function ($p) use ($user) {
                // Resolve once; derive "free" purely from the resolved
                // amount in the visitor's currency, NOT the legacy USD
                // column. Otherwise a plan priced ₹0 (free in INR) but
                // $4.99 (paid in USD) would render inconsistently.
                $monthly = PricingResolver::priceFor($p, $user, 'monthly');
                return [
                    'name'        => $p->name,
                    'description' => $p->description,
                    'features'    => $p->features ?? [],
                    'is_free'     => $monthly['amount_minor'] <= 0,
                    'monthly'     => $monthly,
                ];
            });

        return view('home', compact('plans', 'currency'));
    }
}
