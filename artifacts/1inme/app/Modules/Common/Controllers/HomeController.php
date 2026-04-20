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
                return [
                    'name'        => $p->name,
                    'description' => $p->description,
                    'features'    => $p->features ?? [],
                    'is_free'     => (float) $p->monthly_price <= 0,
                    'monthly'     => PricingResolver::priceFor($p, $user, 'monthly'),
                ];
            });

        return view('home', compact('plans', 'currency'));
    }
}
