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
        $items = Plan::orderBy('sort_order')->orderBy('id')->get()->map(fn ($p) => [
            'id'             => $p->id,
            'slug'           => $p->slug,
            'name'           => $p->name,
            'price_monthly'  => $p->price_monthly ?? null,
            'price_yearly'   => $p->price_yearly ?? null,
            'currency'       => $p->currency ?? 'USD',
            'features'       => $p->features ?? [],
            'is_active'      => (bool) ($p->is_active ?? true),
            'sort_order'     => $p->sort_order ?? 0,
        ])->all();
        return $this->ok(['items' => $items]);
    }
}
