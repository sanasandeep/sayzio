<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\Plan;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlanController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        // Public self-serve catalog: never expose "internal" (admin-only)
        // plans here (see Plan::scopePublic()).
        $platform = \App\Services\PlatformPricing::platform($request);
        $currency = \App\Services\PricingResolver::currencyForUser($request->user());
        $items = Plan::public()->with('prices')->orderBy('sort_order')->orderBy('id')->get()->map(function ($p) use ($platform,$currency) {
            $monthly = \App\Services\PlatformPricing::price($p,$currency,'monthly',$platform);
            $annual = \App\Services\PlatformPricing::price($p,$currency,'annual',$platform);
            return ['id'=>$p->id,'slug'=>$p->slug,'name'=>$p->name,
                'price_monthly'=>$monthly['amount_minor']/100,'price_yearly'=>$annual['amount_minor']/100,
                'monthly_amount_minor'=>$monthly['amount_minor'],'annual_amount_minor'=>$annual['amount_minor'],
                'currency'=>$currency,'platform'=>$platform,'features'=>$p->features ?? [],'is_active'=>true,'sort_order'=>$p->sort_order ?? 0];
        })->all();
        return $this->ok(['items' => $items]);
    }
}
