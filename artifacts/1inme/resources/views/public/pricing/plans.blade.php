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
    .grad-glow.is-current::before {
        opacity: 1;
        background: linear-gradient(135deg,#34d399,#22d3ee);
    }
    .price-num { font-variant-numeric: tabular-nums; }
    .price-pop { animation: pricePop .35s cubic-bezier(.2,.7,.2,1); }
    @keyframes pricePop {
        0%   { transform: translateY(6px); opacity: 0; }
        100% { transform: translateY(0); opacity: 1; }
    }
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

    /* Most-popular ribbon — gentle wiggle on hover */
    .pop-ribbon { animation: ribbonGlow 3s ease-in-out infinite; }
    @keyframes ribbonGlow {
        0%, 100% { box-shadow: 0 8px 24px -10px rgba(236,72,153,.55); }
        50%      { box-shadow: 0 12px 32px -8px rgba(167,139,250,.65); }
    }
    .grad-glow:hover .pop-ribbon { animation: ribbonWiggle .55s ease-in-out; }
    @keyframes ribbonWiggle {
        0%,100% { transform: translate(-50%, 0) rotate(0); }
        25%     { transform: translate(-50%, -1px) rotate(-3deg); }
        75%     { transform: translate(-50%, -1px) rotate(3deg); }
    }

    /* "Recommended for you" callout in the upgrade banner */
    .smart-banner {
        background:
            radial-gradient(60% 100% at 90% 0%, rgba(124,58,237,.20), transparent 60%),
            radial-gradient(50% 100% at 0% 100%, rgba(236,72,153,.18), transparent 60%),
            linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.015));
    }
    .smart-pill {
        background: linear-gradient(90deg, rgba(167,139,250,.28), rgba(236,72,153,.24), rgba(34,211,238,.24));
        border: 1px solid rgba(255,255,255,.18);
    }
    .smart-meter {
        height: 6px; background: rgba(255,255,255,.06); border-radius: 9999px; overflow: hidden;
    }
    .smart-meter > span {
        display: block; height: 100%; border-radius: 9999px;
        background: linear-gradient(90deg,#7c3aed,#ec4899,#f59e0b);
        transition: width .9s cubic-bezier(.2,.7,.2,1);
    }
    .smart-meter.warn > span { background: linear-gradient(90deg,#f59e0b,#ef4444); }

    /* Compare-features matrix — one column per plan */
    .feat-matrix-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .feat-matrix { min-width: 720px; }
    .feat-cell {
        padding: .85rem 1rem; border-top: 1px solid rgba(255,255,255,.05);
        font-size: .82rem; color: #cbd5e1;
    }
    .feat-cell.feat-head {
        border-top: 0; background: rgba(255,255,255,.03);
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .68rem; font-weight: 700; color: #94a3b8;
    }
    .feat-cell.feat-row-name {
        position: sticky; left: 0; background: #0b0a14;
        z-index: 2; color: #e5e7eb; font-weight: 500;
    }
    .feat-cell.feat-group {
        background: rgba(124,58,237,.08); color: #c4b5fd;
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .66rem; font-weight: 700;
        padding: .55rem 1rem;
    }
    .feat-cell.feat-popular-col {
        background: linear-gradient(180deg, rgba(124,58,237,.10), transparent);
    }
    .feat-mark { display: inline-flex; align-items: center; justify-content: center;
                 width: 26px; height: 26px; border-radius: 9999px; }
    .feat-mark-yes { background: rgba(16,185,129,.14); color: #34d399; }
    .feat-mark-no  { background: rgba(148,163,184,.10); color: #64748b; }

    @media (prefers-reduced-motion: reduce) {
        .pulse-dot, .float-coin, .pop-ribbon, .grad-glow { animation: none !important; }
        .grad-glow:hover { transform: none !important; }
        .price-pop { animation: none !important; }
        .smart-meter > span { transition: none !important; }
    }
</style>
@endpush

@section('content')
<section
    x-data="{
        cycle: '{{ $cycle }}',
        priceKey: 0,
        money(plan, c){
            const r = c === 'annual' ? plan.annual : plan.monthly;
            return r && r.formatted ? r.formatted : '—';
        },
        trackCoinsView(){
            const url = '{{ route('marketing-events.track') }}';
            const data = new FormData();
            data.append('source', 'landing_pricing_teaser');
            data.append('target', 'coins');
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, data);
            } else {
                fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
            }
        }
    }"
    x-init="
        $watch('cycle', () => priceKey++);
        // Coin packages no longer live behind a tab toggle; instead
        // we fire the marketing event when the coins section either
        // is the deep-linked target on load or scrolls into view.
        const fireOnceCoins = (() => { let fired = false; return () => { if (!fired) { fired = true; trackCoinsView(); } }; })();
        if (window.location.hash === '#coins') { fireOnceCoins(); }
        const coinsEl = document.getElementById('coins');
        if (coinsEl && 'IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) { fireOnceCoins(); io.disconnect(); } });
            }, { rootMargin: '0px 0px -25% 0px' });
            io.observe(coinsEl);
        }
    "
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
        <div class="text-center max-w-3xl mx-auto space-y-3" data-anim="fade-up">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-xs font-bold uppercase tracking-[.18em] text-violet-300">
                <span class="w-1.5 h-1.5 rounded-full bg-violet-400 pulse-dot"></span>
                Pricing &amp; coins
            </div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">
                Pick a plan. <span class="grad-text">Top up coins.</span>
            </h1>
            <p class="text-lg text-gray-400">
                Plans for steady use, coins for one-off boosts — all in one place.
            </p>
            @include('public.pricing._currency_badge', [
                'currency'       => $currency,
                'currencySource' => $currencySource,
                'user'           => $user,
                'switchRoute'    => 'upgrade.public.switch-currency',
            ])
        </div>

        {{-- ───────────────── SMART UPGRADE BANNER (logged-in only) ───────────────── --}}
        @auth
            @php $rec = $recommendation; @endphp
            @if($rec)
                <div data-anim="fade-up" class="smart-banner mt-10 rounded-2xl border border-white/10 p-5 sm:p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-6">
                        {{-- Left: current plan + usage gauges --}}
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[.18em] text-violet-300 mb-1">
                                <i class="fas fa-user-circle"></i> You're signed in
                            </div>
                            <div class="text-white text-lg font-semibold">
                                You're on
                                <span class="smart-pill inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-sm align-middle">
                                    <i class="fas fa-bolt text-amber-300 text-xs"></i> {{ $rec['currentPlan']?->name ?? 'no plan' }}
                                </span>
                            </div>
                            @if(!empty($rec['usage']))
                                <div class="mt-4 space-y-2.5">
                                    @foreach($rec['usage'] as $u)
                                        <div>
                                            <div class="flex items-baseline justify-between text-xs">
                                                <span class="text-gray-300 capitalize">{{ $u['label'] }}</span>
                                                <span class="text-gray-400">
                                                    @if($u['unlimited'])
                                                        <span class="text-emerald-300 font-semibold">{{ number_format($u['used']) }}</span> · unlimited
                                                    @else
                                                        <span class="text-white font-semibold">{{ number_format($u['used']) }}</span> / {{ number_format($u['cap']) }}
                                                        <span class="text-gray-500">({{ $u['pct'] }}%)</span>
                                                    @endif
                                                </span>
                                            </div>
                                            @unless($u['unlimited'])
                                                <div class="smart-meter mt-1 {{ $u['pct'] >= 70 ? 'warn' : '' }}">
                                                    <span style="width: {{ max(2, $u['pct']) }}%"></span>
                                                </div>
                                            @endunless
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-3 text-sm text-gray-400">Start adding links and bio pages to see how much room your plan has.</p>
                            @endif
                        </div>

                        {{-- Right: recommendation card --}}
                        @if($rec['recommendedPlan'])
                            @php $recPlan = $rec['recommendedPlan']; @endphp
                            <a href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                               class="group block rounded-2xl border border-violet-400/40 p-5 bg-gradient-to-br from-violet-600/15 via-pink-500/10 to-transparent hover:from-violet-600/25 hover:to-amber-500/10 transition relative overflow-hidden">
                                <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-violet-500/20 blur-2xl pointer-events-none"></div>
                                <div class="text-[11px] font-bold uppercase tracking-[.18em] text-pink-300 mb-1">
                                    <i class="fas fa-wand-magic-sparkles"></i> Recommended for you
                                </div>
                                <div class="flex items-baseline justify-between gap-3 mt-1">
                                    <div>
                                        <div class="text-2xl font-bold text-white">Upgrade to {{ $recPlan->name }}</div>
                                        <div class="text-sm text-gray-300 mt-1">{{ $rec['reason'] }}</div>
                                    </div>
                                    <i class="fas fa-arrow-right text-pink-300 group-hover:translate-x-1 transition"></i>
                                </div>
                                <div class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 text-xs font-semibold text-white">
                                    See plans &amp; checkout <i class="fas fa-chevron-right text-[10px]"></i>
                                </div>
                            </a>
                        @else
                            <div class="rounded-2xl border border-emerald-400/30 p-5 bg-emerald-400/5 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-400/15 flex items-center justify-center text-emerald-300">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="text-emerald-100">
                                    <div class="font-semibold">You're on our top tier.</div>
                                    <div class="text-sm text-emerald-200/80">Add coin packs below for one-off boosts.</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endauth

        {{-- Cycle toggle --}}
        <div class="flex items-center justify-center gap-3 mt-10" data-anim="fade-up">
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

        {{-- ───────────────── PLANS GRID ─────────────────
             Tailwind safelist note: dynamic interpolation of
             `lg:grid-cols-{N}` is brittle because the JIT compiler can't
             see the class string at build time. We use explicit
             conditional classes here so the cards always sit on a single
             row at lg+ regardless of how many active plans the seeder
             surfaces. --}}
        @php
            $planCount = count($plans);
            // Tailwind safelist note: dynamic interpolation of
            // `lg:grid-cols-{N}` is brittle because the JIT compiler
            // can't see the class string at build time. We use explicit
            // conditional classes so all active plans sit in a single
            // row at lg+ regardless of how many tiers the seeder
            // surfaces. We support up to 6 columns (the catalogue
            // currently ships 5: free / starter / pro / business /
            // enterprise — clamping at 6 prevents over-cramping).
            $lgGrid = match (true) {
                $planCount >= 6 => 'lg:grid-cols-6',
                $planCount === 5 => 'lg:grid-cols-5',
                $planCount === 4 => 'lg:grid-cols-4',
                $planCount === 3 => 'lg:grid-cols-3',
                default => 'lg:grid-cols-2',
            };
            $hasRec = isset($recommendation['recommendedPlan']) && $recommendation['recommendedPlan'];
            $recPlanId = $hasRec ? $recommendation['recommendedPlan']->id : null;
            $currentPlanId = $recommendation['currentPlan']->id ?? null;
        @endphp
        <div data-anim="fade-up"
             class="grid grid-cols-1 md:grid-cols-2 {{ $lgGrid }} gap-5 mt-8 items-stretch">
            @foreach($plans as $row)
                @php
                    $plan = $row['model'];
                    $features = $plan->features ?? [];
                    $isPopular = $plan->is_popular;
                    $isCurrent = $currentPlanId === $plan->id;
                    $isRecommended = $recPlanId === $plan->id && !$isCurrent;
                    $cmpKind = auth()->check()
                        ? \App\Services\PlanRecommender::compare($recommendation['currentPlan'] ?? null, $plan)
                        : 'guest';
                    $planJs = [
                        'monthly' => $row['monthly'],
                        'annual'  => $row['annual'],
                    ];
                    $borderClasses = $isCurrent
                        ? 'border-emerald-400/50 bg-gradient-to-b from-emerald-500/[0.10] to-transparent'
                        : ($isRecommended
                            ? 'border-pink-400/50 bg-gradient-to-b from-pink-500/[0.10] to-transparent'
                            : ($isPopular
                                ? 'border-violet-500/50 bg-gradient-to-b from-violet-500/[0.10] to-transparent'
                                : 'border-white/10 bg-white/[0.02]'));
                @endphp
                <div
                    x-data='{ plan: @json($planJs) }'
                    class="grad-glow {{ $isPopular || $isRecommended ? 'is-popular' : '' }} {{ $isCurrent ? 'is-current' : '' }} group relative rounded-2xl border {{ $borderClasses }} p-6 flex flex-col">
                    @if($isCurrent)
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap px-3 py-1 bg-emerald-500 text-white text-[10px] font-bold rounded-full uppercase tracking-wider shadow-lg shadow-emerald-500/20">
                            <i class="fas fa-circle-check mr-1"></i> Your plan
                        </div>
                    @elseif($isRecommended)
                        <div class="pop-ribbon absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap px-3 py-1 grad-bar text-white text-[10px] font-bold rounded-full uppercase tracking-wider">
                            <i class="fas fa-wand-magic-sparkles mr-1"></i> Recommended
                        </div>
                    @elseif($isPopular)
                        <div class="pop-ribbon absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap px-3 py-1 grad-bar text-white text-[10px] font-bold rounded-full uppercase tracking-wider">
                            <i class="fas fa-star mr-1"></i> Most popular
                        </div>
                    @endif
                    <div class="text-xs uppercase tracking-wider text-gray-400">{{ $plan->name }}</div>
                    <div class="flex items-baseline gap-1 mt-2">
                        <span class="price-num text-4xl font-semibold text-white"
                              :class="'price-pop'"
                              :key="cycle + '-' + priceKey + '-{{ $plan->id }}'"
                              x-text="money(plan, cycle)">{{ $row[$cycle]['formatted'] ?? '—' }}</span>
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
                        @foreach([
                            'max_links' => 'links',
                            'max_biolinks' => 'bio pages',
                            'max_projects' => 'projects',
                            'storage_limit_mb' => 'MB storage',
                            'contacts_max' => 'contacts',
                            'max_forms' => 'forms',
                            'max_files' => 'files',
                            'max_vault_items' => 'vault items',
                            'max_task_boards' => 'task boards',
                            'max_leads' => 'leads',
                            'max_events' => 'events',
                            'max_buzz_items' => 'buzz popups',
                            'max_splash_pages' => 'splash pages',
                        ] as $key => $label)
                            @if(isset($features[$key]))
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-violet-400 text-xs mt-1"></i>
                                    <span>{{ (int) $features[$key] === -1 ? 'Unlimited' : number_format((int) $features[$key]) }} {{ $label }}</span>
                                </li>
                            @endif
                        @endforeach
                        @foreach([
                            'creator_profile_public' => 'Public creator profile',
                            'calendar_sync' => 'Calendar sync',
                            'verification_eligible' => 'Verified-creator eligible',
                            'link_password' => 'Password-protected links',
                            'link_expiry' => 'Link expiry',
                            'link_geo_targeting' => 'Geo targeting',
                            'link_device_targeting' => 'Device targeting',
                            'link_deep_link' => 'Deep links',
                            'link_smart_rules' => 'Smart redirect rules',
                            'link_active_window' => 'Active-window scheduling',
                        ] as $key => $label)
                            @if(!empty($features[$key]))
                                <li class="flex items-start gap-2">
                                    <i class="fas fa-check-circle text-violet-400 text-xs mt-1"></i>
                                    <span>{{ $label }}</span>
                                </li>
                            @endif
                        @endforeach
                        @if(!empty($features['block_types_allowed']) && $features['block_types_allowed'] !== '*' && is_array($features['block_types_allowed']))
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle text-violet-400 text-xs mt-1"></i>
                                <span>{{ count($features['block_types_allowed']) }} biolink block types</span>
                            </li>
                        @elseif(($features['block_types_allowed'] ?? null) === '*')
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle text-violet-400 text-xs mt-1"></i>
                                <span>All biolink block types</span>
                            </li>
                        @endif
                    </ul>
                    @endif

                    <div class="mt-6">
                        @switch($cmpKind)
                            @case('current')
                                <span class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold bg-emerald-500/15 text-emerald-200 border border-emerald-400/30">
                                    <i class="fas fa-check mr-1"></i> Current plan
                                </span>
                                @break
                            @case('upgrade')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                                   href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                                   class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold grad-bar text-white hover:opacity-95 transition">
                                    Upgrade to {{ $plan->name }} <i class="fas fa-arrow-up ml-1 text-xs"></i>
                                </a>
                                @break
                            @case('downgrade')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                                   href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                                   class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold bg-white/10 text-white hover:bg-white/20 transition">
                                    Downgrade to {{ $plan->name }}
                                </a>
                                @break
                            @case('choose')
                                <a :href="'{{ route('user.upgrade') }}?cycle=' + cycle"
                                   href="{{ route('user.upgrade', ['cycle' => $cycle]) }}"
                                   class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold {{ $isPopular ? 'grad-bar text-white hover:opacity-95' : 'bg-white/10 text-white hover:bg-white/20' }} transition">
                                    Choose {{ $plan->name }}
                                </a>
                                @break
                            @default
                                <a href="{{ route('user.register') }}"
                                   class="block text-center w-full px-4 py-2.5 rounded-xl font-semibold {{ $isPopular ? 'grad-bar text-white hover:opacity-95' : 'bg-white/10 text-white hover:bg-white/20' }} transition">
                                    {{ ((int) ($row['monthly']['amount_minor'] ?? 0)) === 0 ? 'Get started free' : 'Start free trial' }}
                                </a>
                        @endswitch
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ───────────────── COMPARE FEATURES AT A GLANCE ───────────────── --}}
        @php
            // Plan-vs-plan feature matrix. Reuses the same labels from
            // the per-card lists above so visitors can scan deltas without
            // hunting through cards.
            $matrixGroups = [
                'Limits' => [
                    ['max_links',         'Short links',          'number'],
                    ['max_biolinks',      'Link in Bio pages',    'number'],
                    ['max_projects',      'Projects',             'number'],
                    ['storage_limit_mb',  'Storage (MB)',         'number'],
                    ['contacts_max',      'Contacts',             'number'],
                    ['max_files',         'Files',                'number'],
                    ['max_forms',         'Forms',                'number'],
                ],
                'Growth & analytics' => [
                    ['analytics',                'Analytics depth',         'analytics'],
                    ['pixels',                   'Marketing pixels',        'bool'],
                    ['utm_params',               'UTM parameters',          'bool'],
                    ['custom_domains',           'Custom domains',          'bool'],
                    ['seo_settings',             'SEO & social previews',   'bool'],
                ],
                'Per-link controls' => [
                    ['link_password',            'Password protection',     'bool'],
                    ['link_expiry',              'Link expiry',             'bool'],
                    ['link_geo_targeting',       'Geo targeting',           'bool'],
                    ['link_device_targeting',    'Device targeting',        'bool'],
                    ['link_deep_link',           'Deep links',              'bool'],
                    ['link_smart_rules',         'Smart redirect rules',    'bool'],
                ],
                'Monetization & teams' => [
                    ['ecommerce',                'Sell from your bio',      'bool'],
                    ['custom_forms',             'Custom forms',            'bool'],
                    ['teams',                    'Team workspaces',         'bool'],
                    ['leads',                    'Leads capture',           'bool'],
                    ['vaults',                   'Credential vault',        'bool'],
                    ['creator_profile_public',   'Public creator profile',  'bool'],
                ],
            ];
            $colTpl = 'minmax(180px, 1.4fr) repeat(' . count($plans) . ', minmax(120px, 1fr))';
            $renderCell = function ($plan, $key, $kind) {
                $features = $plan->features ?? [];
                if (!array_key_exists($key, $features) && $kind !== 'analytics') {
                    return '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-times text-[10px]"></i></span>';
                }
                $val = $features[$key] ?? null;
                if ($kind === 'number') {
                    if ((int) $val === -1) return '<span class="text-emerald-300 font-semibold">Unlimited</span>';
                    if ((int) $val === 0)  return '<span class="feat-mark feat-mark-no"><i class="fas fa-times text-[10px]"></i></span>';
                    return '<span class="text-white font-semibold">' . number_format((int) $val) . '</span>';
                }
                if ($kind === 'analytics') {
                    $label = is_string($val) ? ucfirst($val) : 'Basic';
                    $cls = strtolower((string) $val) === 'advanced' ? 'text-emerald-300 font-semibold' : 'text-gray-300';
                    return '<span class="' . $cls . '">' . e($label) . '</span>';
                }
                return $val
                    ? '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>'
                    : '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-times text-[10px]"></i></span>';
            };
        @endphp
        <div class="mt-16" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-6">
                <div class="text-xs font-bold uppercase tracking-[.2em] text-violet-400 mb-2">Side by side</div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Compare features at a glance</h2>
                <p class="text-gray-400 mt-2">Every plan, every important feature — laid out so you can spot the deltas in seconds.</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-white/[0.02] overflow-hidden">
                <div class="feat-matrix-scroll">
                    <div class="feat-matrix grid" style="grid-template-columns: {{ $colTpl }};">
                        {{-- Header row --}}
                        <div class="feat-cell feat-head feat-row-name">Feature</div>
                        @foreach($plans as $row)
                            @php $p = $row['model']; @endphp
                            <div class="feat-cell feat-head text-center {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                <span class="text-white text-sm font-semibold normal-case tracking-normal">
                                    @if($p->is_popular)<i class="fas fa-star text-pink-400 text-[10px]"></i>@endif
                                    {{ $p->name }}
                                </span>
                            </div>

                        @endforeach

                        {{-- Grouped rows --}}
                        @foreach($matrixGroups as $groupName => $rows)
                            <div class="feat-cell feat-group" style="grid-column: span {{ count($plans) + 1 }};">{{ $groupName }}</div>
                            @foreach($rows as [$fkey, $flabel, $fkind])
                                <div class="feat-cell feat-row-name">{{ $flabel }}</div>
                                @foreach($plans as $prow)
                                    @php $p = $prow['model']; @endphp
                                    <div class="feat-cell text-center {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                        {!! $renderCell($p, $fkey, $fkind) !!}
                                    </div>
                                @endforeach
                            @endforeach
                        @endforeach
                    </div>
                </div>
                <div class="md:hidden text-center text-[11px] text-gray-500 px-4 py-3 bg-white/[.02] border-t border-white/5">
                    <i class="fas fa-arrows-left-right"></i> Swipe to see all plans
                </div>
            </div>
        </div>

        {{-- ───────────────── COIN PACKAGES SECTION (always visible) ───────────────── --}}
        <div id="coins" class="mt-20" data-anim="fade-up">
            <div class="text-center max-w-2xl mx-auto mb-8">
                <div class="text-xs font-bold uppercase tracking-[.2em] text-amber-300 mb-2">
                    <i class="fas fa-coins"></i> Top up with coins
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">No subscription? Just grab some coins.</h2>
                <p class="text-gray-400 mt-2">
                    Coins activate paid add-ons on demand — perfect for one-off campaigns,
                    NFC tag batches, AI credit top-ups or activating a single feature without
                    committing to a plan.
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
                @php
                    /**
                     * "Best for…" caption catalogue. Picks the right tagline for
                     * each pack based on its size band so visitors immediately
                     * see what each pack is typically used for.
                     */
                    $bestForFor = function ($row) {
                        $total = (int) ($row['total_coins'] ?? 0);
                        if ($total <= 0) return 'Activating a single paid add-on without subscribing.';
                        if ($total < 1000) return 'One-off campaigns or activating a single add-on.';
                        if ($total < 5000) return 'A batch of NFC tag activations or a short ad burst.';
                        if ($total < 20000) return 'AI credit top-ups + a few add-on activations.';
                        return 'Power-users — months of AI generation, gifting credits, or running multiple add-ons in parallel.';
                    };
                @endphp
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

                            <div class="mt-3 rounded-lg bg-white/[0.03] border border-white/5 px-3 py-2">
                                <div class="text-[10px] uppercase tracking-wider text-amber-300/80 font-bold mb-0.5">
                                    <i class="fas fa-bullseye"></i> Best for
                                </div>
                                <div class="text-xs text-gray-300 leading-snug">{{ $bestForFor($row) }}</div>
                            </div>

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
        <div class="mt-14 text-center" data-anim="fade-up">
            <p class="text-gray-400">Want the full feature breakdown?</p>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.premium-features') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium transition">
                    <i class="fas fa-star text-violet-400"></i> See premium features
                </a>
                <a href="#coins" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium transition">
                    <i class="fas fa-coins text-amber-400"></i> Browse coin packages
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ============================ HEAD-TO-HEAD ONLY (compact) ============================
     The 24-feature matrix is intentionally hidden on the pricing page (the
     "Compare features at a glance" matrix above already serves that purpose
     for plans). We force the compact path so visitors only see the more
     creative 1-on-1 rival selector. --}}
@include('public.partials._compare', ['compact' => true, 'anchorId' => 'compare'])
@endsection
