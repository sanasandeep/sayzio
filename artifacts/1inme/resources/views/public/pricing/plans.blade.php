@extends('public.layouts.site')

@section('title', 'Pricing')

@push('head')
<style>
    .grad-bar { background: linear-gradient(135deg,#7c3aed 0%,#ec4899 50%,#f59e0b 100%); }
    .grad-text { background: linear-gradient(90deg,#a78bfa,#f472b6,#fbbf24); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .grad-glow { position: relative; }
    .grad-glow::before {
        content: ""; position: absolute; inset: -1px; border-radius: 1.1rem; padding: 1px;
        background: linear-gradient(135deg,#7c3aed,#ec4899,#f59e0b);
        -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
        -webkit-mask-composite: xor; mask-composite: exclude;
        opacity: 0; transition: opacity .35s ease;
        pointer-events: none;
    }
    .grad-glow:hover::before, .grad-glow.is-popular::before { opacity: 1; }
    .grad-glow:hover { transform: translateY(-4px); }
    .grad-glow { transition: transform .35s cubic-bezier(.2,.7,.2,1); }
    .price-num { font-variant-numeric: tabular-nums; }
    .coin-bg {
        background:
          radial-gradient(70% 60% at 30% 20%, rgba(245,158,11,.12), transparent 60%),
          radial-gradient(60% 50% at 80% 80%, rgba(236,72,153,.10), transparent 60%);
    }
    .pulse-dot { animation: pulse 1.6s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.5} }
    .float-coin { animation: floaty 3s ease-in-out infinite; }
    @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
    .seg { transition: background-color .25s ease, color .25s ease, box-shadow .25s ease; }
    .seg-active { background-image: linear-gradient(135deg,#7c3aed,#ec4899); color: #fff; box-shadow: 0 8px 24px -10px rgba(236,72,153,.55); }
</style>
@endpush

@section('content')
<section
    x-data="{
        cycle: '{{ $cycle }}',
        view: 'plans',
        money(plan, c){
            const r = c === 'annual' ? plan.annual : plan.monthly;
            return r && r.formatted ? r.formatted : '—';
        },
        amount(plan, c){
            const r = c === 'annual' ? plan.annual : plan.monthly;
            return r ? Number(r.amount_minor || 0) : 0;
        },
        equivPerMonth(plan){
            const a = (plan.annual && plan.annual.amount_minor) ? Number(plan.annual.amount_minor)/12 : 0;
            return a;
        }
    }"
    class="relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="absolute inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[60rem] h-[60rem] rounded-full opacity-30 blur-[120px]"
             style="background: radial-gradient(closest-side, #7c3aed 0%, transparent 70%);"></div>
        <div class="absolute top-40 -right-32 w-[36rem] h-[36rem] rounded-full opacity-20 blur-[100px]"
             style="background: radial-gradient(closest-side, #ec4899 0%, transparent 70%);"></div>
        <div class="absolute top-72 -left-32 w-[36rem] h-[36rem] rounded-full opacity-20 blur-[100px]"
             style="background: radial-gradient(closest-side, #f59e0b 0%, transparent 70%);"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-xs font-bold uppercase tracking-[.18em] text-violet-300">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-400 pulse-dot"></span>
                Pricing &amp; coins
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">
                Pick a plan. <span class="grad-text">Top up coins.</span>
            </h1>
            <p class="text-lg text-gray-400">
                Plans for steady use, coins for one-off boosts — all in one place.
                Prices in <span class="text-white font-medium">{{ $currency }}</span>@if($user && $user->country) for <span class="uppercase">{{ $user->country }}</span>@endif.
            </p>
        </div>

        {{-- Plans / Coins switcher --}}
        <div class="mt-8 flex items-center justify-center">
            <div class="inline-flex rounded-full border border-white/10 bg-white/[0.03] p-1 backdrop-blur">
                <button type="button" @click="view='plans'"
                    :class="view==='plans' ? 'seg-active' : 'text-gray-300 hover:text-white'"
                    class="seg px-5 py-2 text-sm rounded-full inline-flex items-center gap-2">
                    <i class="fas fa-tags text-xs"></i> Subscription plans
                </button>
                <button type="button" @click="view='coins'"
                    :class="view==='coins' ? 'seg-active' : 'text-gray-300 hover:text-white'"
                    class="seg px-5 py-2 text-sm rounded-full inline-flex items-center gap-2">
                    <i class="fas fa-coins text-xs"></i> Coin packages
                </button>
            </div>
        </div>

        {{-- Cycle toggle (only for plans) --}}
        <div x-show="view==='plans'" x-transition class="flex items-center justify-center gap-3 mt-5">
            <div class="inline-flex rounded-full border border-white/10 bg-white/[0.02] p-1">
                <button type="button" @click="cycle='monthly'"
                    :class="cycle==='monthly' ? 'seg-active' : 'text-gray-300 hover:text-white'"
                    class="seg px-5 py-2 text-sm rounded-full">Monthly</button>
                <button type="button" @click="cycle='annual'"
                    :class="cycle==='annual' ? 'seg-active' : 'text-gray-300 hover:text-white'"
                    class="seg px-5 py-2 text-sm rounded-full inline-flex items-center gap-2">
                    Annual
                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 text-[10px] font-bold uppercase">save 2 months</span>
                </button>
            </div>
        </div>

        @if(!$user || !$user->country)
        <form method="POST" action="{{ route('upgrade.public.switch-currency') }}" class="flex items-center justify-center gap-2 mt-4" x-show="view==='plans'" x-transition>
            @csrf
            <span class="text-xs uppercase tracking-wider text-gray-500">Show prices in:</span>
            <button type="submit" name="currency" value="USD" class="px-3 py-1 text-xs rounded-l-full border border-white/10 {{ $currency === 'USD' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">USD ($)</button>
            <button type="submit" name="currency" value="INR" class="px-3 py-1 text-xs rounded-r-full border border-white/10 border-l-0 {{ $currency === 'INR' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}">INR (₹)</button>
        </form>
        @endif

        {{-- ───────────────── PLANS GRID ───────────────── --}}
        <div x-show="view==='plans'" x-transition.opacity.duration.300ms
             class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ max(2, min(count($plans), 4)) }} gap-5 mt-10">
            @foreach($plans as $row)
                @php
                    $plan = $row['model'];
                    $features = $plan->features ?? [];
                    $isPopular = $plan->is_popular;
                    $planJs = [
                        'monthly' => $row['monthly'],
                        'annual'  => $row['annual'],
                    ];
                @endphp
                <div
                    x-data='{ plan: @json($planJs) }'
                    class="grad-glow {{ $isPopular ? 'is-popular' : '' }} group relative rounded-2xl border {{ $isPopular ? 'border-violet-500/50 bg-gradient-to-b from-violet-500/[0.10] to-transparent' : 'border-white/10 bg-white/[0.02]' }} p-6 flex flex-col">
                    @if($isPopular)
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 grad-bar text-white text-[10px] font-bold rounded-full uppercase tracking-wider shadow-lg shadow-pink-500/20">
                            <i class="fas fa-star mr-1"></i> Most popular
                        </div>
                    @endif
                    <div class="text-xs uppercase tracking-wider text-gray-400">{{ $plan->name }}</div>
                    <div class="flex items-baseline gap-1 mt-2">
                        <span class="price-num text-4xl font-semibold text-white" x-text="money(plan, cycle)">{{ $row[$cycle]['formatted'] ?? '—' }}</span>
                        <span class="text-sm text-gray-500">/ <span x-text="cycle==='annual' ? 'yr' : 'mo'">{{ $cycle === 'annual' ? 'yr' : 'mo' }}</span></span>
                    </div>
                    <div class="text-[11px] text-emerald-300/80 mt-1 h-4"
                         x-show="cycle==='annual' && plan.annual && Number(plan.annual.amount_minor) > 0"
                         x-text="'≈ ' + (plan.annual ? (Number(plan.annual.amount_minor)/12/100).toLocaleString(undefined,{style:'currency',currency:plan.annual.currency || '{{ $currency }}'}) : '') + '/mo billed annually'"></div>

                    {{-- Tax / fineprint blocks for each cycle, toggled by Alpine --}}
                    @foreach(['monthly','annual'] as $c)
                        @php $taxKey = 'tax_'.$c; @endphp
                        @if(($row[$c]['amount_minor'] ?? 0) > 0)
                            <div x-show="cycle==='{{ $c }}'" x-cloak>
                                @if(!empty($row[$taxKey]) && !empty($row[$taxKey]['tax_breakdown']))
                                    <div class="mt-2 text-[11px] text-gray-400 space-y-0.5 border-t border-white/5 pt-2">
                                        @foreach($row[$taxKey]['tax_breakdown'] as $line)
                                            <div class="flex justify-between"><span>+ {{ $line['label'] }}</span><span>{{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $currency) }}</span></div>
                                        @endforeach
                                        <div class="flex justify-between font-medium text-white pt-1"><span>Total</span><span>{{ \App\Services\PricingResolver::money((int) $row[$taxKey]['grand_total_minor'], $currency) }}</span></div>
                                    </div>
                                @else
                                    <div class="mt-2 text-[11px] text-gray-500">+ taxes as applicable (shown at checkout)</div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                    <p class="text-sm text-gray-400 mt-3 min-h-[2.5rem]">{{ $plan->description }}</p>

                    @if(!empty($features))
                    <ul class="mt-4 space-y-1.5 text-sm text-gray-200 flex-grow">
                        @foreach(['max_links' => 'links', 'max_biolinks' => 'bio pages', 'max_projects' => 'projects', 'storage_limit_mb' => 'MB storage', 'contacts_max' => 'contacts'] as $key => $label)
                            @if(isset($features[$key]))
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-violet-400 text-xs mt-1"></i>
                                    <span>{{ (int) $features[$key] === -1 ? 'Unlimited' : number_format((int) $features[$key]) }} {{ $label }}</span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    @endif

                    <div class="mt-6">
                        @auth
                            <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                               href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                               class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold {{ $isPopular ? 'grad-bar text-white hover:opacity-95' : 'bg-white/10 text-white hover:bg-white/20' }} transition">
                                Choose {{ $plan->name }}
                            </a>
                        @else
                            <a href="{{ route('user.register') }}"
                               class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold {{ $isPopular ? 'grad-bar text-white hover:opacity-95' : 'bg-white/10 text-white hover:bg-white/20' }} transition">
                                {{ ((int) ($row['monthly']['amount_minor'] ?? 0)) === 0 ? 'Get started free' : 'Start free trial' }}
                            </a>
                        @endauth
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ───────────────── COIN PACKAGES GRID ───────────────── --}}
        <div x-show="view==='coins'" x-transition.opacity.duration.300ms class="mt-10">
            <div class="text-center max-w-2xl mx-auto mb-8">
                <p class="text-gray-400">
                    Coins activate paid add-ons on demand — perfect for one-off campaigns, NFC tag batches or temporary boosts. No subscription required.
                </p>
            </div>

            @if(!$wallet_enabled)
                <div class="max-w-2xl mx-auto rounded-2xl border border-amber-400/20 bg-amber-400/5 p-6 text-amber-200 text-center">
                    <i class="fas fa-coins text-2xl float-coin mb-2 block"></i>
                    Coin top-ups aren't enabled on this site yet — check back soon.
                </div>
            @elseif($packages->isEmpty())
                <div class="max-w-2xl mx-auto rounded-2xl border border-white/10 bg-white/[0.02] p-6 text-gray-400 text-center">
                    No coin packages are available right now.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach($packages as $row)
                        @php $pkg = $row['model']; $isFeat = $pkg->bonus_coins > 0; @endphp
                        <div class="grad-glow {{ $isFeat ? 'is-popular' : '' }} relative rounded-2xl border {{ $isFeat ? 'border-amber-400/40' : 'border-white/10' }} bg-white/[0.02] coin-bg p-6 flex flex-col overflow-hidden">
                            @if($isFeat)
                                <div class="absolute -top-3 right-6 px-3 py-1 bg-amber-400 text-[#1e2330] text-[10px] font-bold rounded-full uppercase tracking-wider shadow-lg shadow-amber-500/20">
                                    +{{ number_format($pkg->bonus_coins) }} bonus
                                </div>
                            @endif
                            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-amber-400/10 blur-2xl pointer-events-none"></div>

                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-amber-400/15 ring-1 ring-amber-400/30 flex items-center justify-center float-coin">
                                    <i class="fas fa-coins text-amber-300"></i>
                                </div>
                                <div class="text-xs uppercase tracking-wider text-gray-400">{{ $pkg->name }}</div>
                            </div>

                            <div class="mt-3 flex items-baseline gap-2">
                                <span class="price-num text-4xl font-semibold text-white">{{ number_format($row['total_coins']) }}</span>
                                <span class="text-amber-300 text-sm">coins</span>
                            </div>
                            @if($pkg->bonus_coins > 0)
                                <div class="text-[11px] text-gray-500 mt-1">{{ number_format($pkg->coin_amount) }} base + <span class="text-amber-300">{{ number_format($pkg->bonus_coins) }} bonus</span></div>
                            @endif
                            @if($pkg->description)
                                <p class="text-sm text-gray-400 mt-3 flex-grow">{{ $pkg->description }}</p>
                            @else
                                <div class="flex-grow"></div>
                            @endif

                            <div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between">
                                <span class="text-2xl font-bold text-white price-num">{{ $row['formatted'] ?? '—' }}</span>
                                @auth
                                    <a href="{{ route('user.wallet.buy') }}" class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300 transition shadow-lg shadow-amber-500/20">Buy now</a>
                                @else
                                    <a href="{{ route('user.register') }}" class="px-4 py-2 bg-amber-400 text-[#1e2330] rounded-xl text-sm font-bold hover:bg-amber-300 transition shadow-lg shadow-amber-500/20">Sign up to buy</a>
                                @endauth
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ───────────────── FOOTER LINKS ───────────────── --}}
        <div class="mt-14 text-center">
            <p class="text-gray-400">Want the full feature breakdown?</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.premium-features') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium transition">
                    <i class="fas fa-star text-violet-400"></i> See premium features
                </a>
                <button type="button" @click="view = view==='plans' ? 'coins' : 'plans'; window.scrollTo({top: 0, behavior:'smooth'})"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium transition">
                    <i class="fas fa-exchange-alt text-pink-400"></i>
                    <span x-text="view==='plans' ? 'Browse coin packages' : 'Compare subscription plans'">Browse coin packages</span>
                </button>
            </div>
        </div>
    </div>
</section>
@endsection
