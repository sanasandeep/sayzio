@extends('user.layouts.app')
@section('title', 'Checkout')
@section('content')
<div class="max-w-3xl mx-auto p-6 space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-white">Checkout</h1>
        <p class="text-sm text-white/50">Review your order and pick a payment method.</p>
    </div>

    @if(session('error'))<div class="px-4 py-2 rounded-xl bg-rose-500/10 border border-rose-400/30 text-rose-200 text-sm">{{ session('error') }}</div>@endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5">
        <h2 class="text-white font-medium mb-3">Your order</h2>
        <div class="divide-y divide-white/5 text-sm">
            @foreach($items as $li)
                @php($intro = $li['meta']['intro_discount'] ?? null)
                @php($term = ($li['meta']['cycle'] ?? null) === 'annual' ? 'year' : 'month')
                <div class="py-2">
                    <div class="flex items-center justify-between text-white/80">
                        <span>{{ $li['label'] }}</span>
                        <span class="font-mono">
                            @if($intro)
                                <span class="text-white/40 line-through mr-1">{{ number_format(($intro['normal_minor'] ?? 0)/100, 2) }}</span>
                            @endif
                            {{ number_format($li['amount_minor']/100, 2) }} {{ $currency }}
                        </span>
                    </div>
                    @if($intro)
                        <div class="flex items-center justify-between text-emerald-300/90 text-xs mt-1">
                            <span>Intro discount@if(!empty($intro['percent_off'])) ({{ $intro['percent_off'] }}% off)@endif</span>
                            <span class="font-mono">−{{ number_format(($intro['amount_off_minor'] ?? 0)/100, 2) }} {{ $currency }}</span>
                        </div>
                        <p class="text-xs text-white/40 mt-1">Renews at {{ number_format(($intro['normal_minor'] ?? 0)/100, 2) }} {{ $currency }}/{{ $term }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-3 pt-3 border-t border-white/10 text-sm space-y-1">
            <div class="flex justify-between text-white/70"><span>Subtotal</span><span class="font-mono">{{ number_format($preview['subtotal_minor']/100, 2) }} {{ $currency }}</span></div>
            @foreach(($preview['tax_breakdown'] ?? []) as $tax)
                <div class="flex justify-between text-white/50"><span>{{ $tax['label'] ?? 'Tax' }} ({{ number_format($tax['rate_percent'] ?? 0, 2) }}%)</span><span class="font-mono">{{ number_format(($tax['amount_minor'] ?? 0)/100, 2) }} {{ $currency }}</span></div>
            @endforeach
            <div class="flex justify-between text-white font-medium pt-2 border-t border-white/5"><span>Total</span><span class="font-mono">{{ number_format($preview['grand_total_minor']/100, 2) }} {{ $currency }}</span></div>
            @if(!empty($preview['reverse_charge_note']))
                <p class="text-xs text-amber-300 pt-1">{{ $preview['reverse_charge_note'] }}</p>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('user.checkout.handoff') }}" class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 space-y-3">
        @csrf
        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
        <input type="hidden" name="cycle" value="{{ $cycle }}">
        @foreach($addons as $a)
            <input type="hidden" name="addons[]" value="{{ $a->id }}">
        @endforeach

        <h2 class="text-white font-medium">Choose a payment method</h2>
        @if(count($gateways) === 0)
            <p class="text-sm text-rose-300">No payment methods are configured yet. Please contact support.</p>
        @endif
        <div class="space-y-2">
            @foreach($gateways as $g)
                <label class="flex items-center gap-3 p-3 rounded-xl border border-white/10 hover:border-violet-400/50 cursor-pointer">
                    <input type="radio" name="gateway" value="{{ $g->slug() }}" required class="accent-violet-500">
                    <span class="text-sm text-white">{{ $g->displayName() }}</span>
                </label>
            @endforeach
        </div>

        <button type="submit" class="w-full px-4 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-xl font-medium">Continue</button>
    </form>
</div>
@endsection
