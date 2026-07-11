@auth
@php
    $__customOffer = \App\Modules\Admin\Models\CustomPlanRequest::pendingOfferForEmail(auth()->user()->email ?? '');
@endphp
@if($__customOffer)
    <div class="mb-4 rounded-xl overflow-hidden"
         style="border:1px solid rgba(61,107,255,0.35);background:linear-gradient(135deg,rgba(61,107,255,0.12) 0%,rgba(59,130,246,0.10) 100%);">
        <div class="p-4 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                 style="background:rgba(61,107,255,0.2);">
                <i class="fas fa-gem text-blue-400 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold" style="color:var(--text-main)">
                    Your custom plan is ready
                </div>
                <div class="text-xs mt-0.5 leading-relaxed" style="color:var(--text-muted)">
                    @if($__customOffer->provisionedPlan)
                        <strong class="text-blue-300">{{ $__customOffer->provisionedPlan->name }}</strong> —
                        @if($__customOffer->offer_cycle === 'annual')
                            ${{ number_format($__customOffer->provisionedPlan->annual_price, 2) }}/year
                        @else
                            ${{ number_format($__customOffer->provisionedPlan->monthly_price, 2) }}/month
                        @endif
                        · {{ ucfirst($__customOffer->offer_cycle ?? 'monthly') }} billing
                    @else
                        A custom plan has been approved for your account.
                    @endif
                </div>
            </div>
            @if($__customOffer->provisionedPlan)
                <a href="{{ route('user.checkout.show') . '?plan=' . $__customOffer->provisioned_plan_id . '&cycle=' . ($__customOffer->offer_cycle ?? 'monthly') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold whitespace-nowrap transition shrink-0"
                   style="background:rgba(61,107,255,0.25);border:1px solid rgba(61,107,255,0.45);color:#93c5fd;">
                    <i class="fas fa-credit-card text-[10px]"></i>
                    Review &amp; Pay
                </a>
            @endif
        </div>
    </div>
@endif
@endauth
