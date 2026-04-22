@extends('public.layouts.site')

@section('title', 'Pricing plans')

@section('content')
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-3">
            <div class="text-xs font-bold uppercase tracking-[.2em] text-violet-400">Pricing plans</div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">Pick the plan that fits your work.</h1>
            <p class="text-lg text-gray-400">All prices in <span class="text-white font-medium">{{ $currency }}</span>@if($user && $user->country) — based on your billing country (<span class="uppercase">{{ $user->country }}</span>).@endif</p>
        </div>

        <div class="flex items-center justify-center gap-3 mt-8">
            <div class="inline-flex rounded-full border border-white/10 bg-white/[0.02] p-1">
                <a href="{{ route('site.pricing', ['cycle' => 'monthly']) }}"
                   class="px-5 py-2 text-sm rounded-full transition {{ $cycle === 'monthly' ? 'bg-violet-600 text-white' : 'text-gray-300 hover:text-white' }}">Monthly</a>
                <a href="{{ route('site.pricing', ['cycle' => 'annual']) }}"
                   class="px-5 py-2 text-sm rounded-full transition {{ $cycle === 'annual' ? 'bg-violet-600 text-white' : 'text-gray-300 hover:text-white' }}">Annual <span class="text-[10px] opacity-70">save 2 months</span></a>
            </div>
        </div>

        @if(!$user || !$user->country)
        <form method="POST" action="{{ route('upgrade.public.switch-currency') }}" class="flex items-center justify-center gap-2 mt-4">
            @csrf
            <span class="text-xs uppercase tracking-wider text-gray-500">Show prices in:</span>
            <button type="submit" name="currency" value="USD" class="px-3 py-1 text-xs rounded-l-full border border-white/10 {{ $currency === 'USD' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">USD ($)</button>
            <button type="submit" name="currency" value="INR" class="px-3 py-1 text-xs rounded-r-full border border-white/10 border-l-0 {{ $currency === 'INR' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">INR (₹)</button>
        </form>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ min(count($plans), 4) }} gap-5 mt-12">
            @foreach($plans as $row)
                @php $plan = $row['model']; $features = $plan->features ?? []; $isPopular = $plan->is_popular; @endphp
                <div class="relative rounded-2xl border {{ $isPopular ? 'border-violet-500/60 ring-1 ring-violet-500/40 bg-violet-500/[0.06]' : 'border-white/10 bg-white/[0.02]' }} p-6 flex flex-col">
                    @if($isPopular)
                        <div class="absolute -top-3 right-6 px-3 py-1 bg-gradient-to-r from-violet-500 to-pink-500 text-white text-[10px] font-bold rounded-full uppercase tracking-wider">Most popular</div>
                    @endif
                    <div class="text-xs uppercase tracking-wider text-gray-400">{{ $plan->name }}</div>
                    <div class="flex items-baseline gap-1 mt-2">
                        <span class="text-3xl font-semibold text-white">{{ $row['shown']['formatted'] }}</span>
                        <span class="text-sm text-gray-500">/ {{ $cycle === 'annual' ? 'yr' : 'mo' }}</span>
                    </div>
                    @if($cycle === 'annual' && $row['monthly']['amount_minor'] > 0)
                        <div class="text-[11px] text-gray-500">vs {{ $row['monthly']['formatted'] }}/mo billed monthly</div>
                    @endif
                    @if($row['shown']['amount_minor'] > 0)
                        @if($row['tax'] && !empty($row['tax']['tax_breakdown']))
                            <div class="mt-2 text-[11px] text-gray-400 space-y-0.5 border-t border-white/5 pt-2">
                                @foreach($row['tax']['tax_breakdown'] as $line)
                                    <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $currency) }}</span></div>
                                @endforeach
                                <div class="flex justify-between font-medium text-white pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $row['tax']['grand_total_minor'], $currency) }}</span></div>
                            </div>
                        @else
                            <div class="mt-2 text-[11px] text-gray-500">+ taxes as applicable (shown at checkout)</div>
                        @endif
                    @endif
                    <p class="text-sm text-gray-400 mt-3 min-h-[2.5rem]">{{ $plan->description }}</p>

                    @if(!empty($features))
                    <ul class="mt-4 space-y-1.5 text-sm text-gray-200 flex-grow">
                        @foreach(['max_links' => 'links', 'max_biolinks' => 'bio pages', 'max_projects' => 'projects', 'storage_limit_mb' => 'MB storage', 'contacts_max' => 'contacts'] as $key => $label)
                            @if(isset($features[$key]))
                                <li class="flex items-start gap-2"><i class="fas fa-check text-violet-400 text-xs mt-1"></i><span>{{ (int) $features[$key] === -1 ? 'Unlimited' : number_format((int) $features[$key]) }} {{ $label }}</span></li>
                            @endif
                        @endforeach
                    </ul>
                    @endif

                    <div class="mt-6">
                        @auth
                            <a href="{{ route('user.upgrade', ['cycle' => $cycle]) }}" class="block text-center w-full px-4 py-2.5 rounded-xl font-medium {{ $isPopular ? 'bg-violet-600 text-white hover:bg-violet-700' : 'bg-white/10 text-white hover:bg-white/20' }} transition">Choose {{ $plan->name }}</a>
                        @else
                            <a href="{{ route('user.register') }}" class="block text-center w-full px-4 py-2.5 rounded-xl font-medium {{ $isPopular ? 'bg-violet-600 text-white hover:bg-violet-700' : 'bg-white/10 text-white hover:bg-white/20' }} transition">{{ ((int) ($row['shown']['amount_minor'] ?? 0)) === 0 ? 'Get started free' : 'Start free trial' }}</a>
                        @endauth
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-14 text-center">
            <p class="text-gray-400">Looking for something else?</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.coins') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium">
                    <i class="fas fa-coins text-amber-400"></i> Browse coin packages
                </a>
                <a href="{{ route('site.premium-features') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium">
                    <i class="fas fa-star text-violet-400"></i> See premium features
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
