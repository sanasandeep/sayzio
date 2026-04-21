<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BillingController extends Controller
{
    use ApiResponses;

    public function subscription(Request $request)
    {
        $sub = Subscription::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->first();
        if (!$sub) return $this->ok(['subscription' => null]);
        return $this->ok(['subscription' => [
            'id'                    => $sub->id,
            'plan_id'               => $sub->plan_id,
            'status'                => $sub->status,
            'billing_cycle'         => $sub->billing_cycle,
            'current_period_start'  => optional($sub->current_period_start)->toIso8601String(),
            'current_period_end'    => optional($sub->current_period_end)->toIso8601String(),
            'cancel_at'             => optional($sub->cancel_at)->toIso8601String(),
            'cancel_at_period_end'  => (bool) $sub->cancel_at_period_end,
            'gateway'               => $sub->gateway,
            'currency'              => $sub->currency,
        ]]);
    }

    public function invoices(Request $request)
    {
        $page = Invoice::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($i) => [
                'id'                => $i->id,
                'number'            => $i->number,
                'status'            => $i->status,
                'currency'          => $i->currency,
                'subtotal_minor'    => (int) ($i->subtotal_minor ?? 0),
                'tax_total_minor'   => (int) ($i->tax_total_minor ?? 0),
                'grand_total_minor' => (int) ($i->grand_total_minor ?? 0),
                'issued_at'         => optional($i->issued_at)->toIso8601String(),
                'paid_at'           => optional($i->paid_at)->toIso8601String(),
            ])->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}
