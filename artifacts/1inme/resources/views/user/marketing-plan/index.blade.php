@extends('user.layouts.app')
@section('title', 'Marketing Plan Calculator')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">
    <style>
        .mpc-card { border: 1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.03); }
        html.light-mode .mpc-card { border-color: rgba(15,23,42,0.12); background: #ffffff; }
        .mpc-title { color: #fff; } html.light-mode .mpc-title { color: #0f172a; }
        .mpc-sub { color: rgba(255,255,255,0.5); } html.light-mode .mpc-sub { color: #475569; }
        .mpc-faint { color: rgba(255,255,255,0.35); } html.light-mode .mpc-faint { color: #64748b; }
        .mpc-menu { background: #0f172a; border: 1px solid rgba(255,255,255,0.10); }
        html.light-mode .mpc-menu { background: #fff; border-color: rgba(15,23,42,0.12); }
        .mpc-menu-item { color: rgba(255,255,255,0.8); }
        .mpc-menu-item:hover { background: rgba(255,255,255,0.10); }
        html.light-mode .mpc-menu-item { color: #1e293b; }
        html.light-mode .mpc-menu-item:hover { background: #f1f5f9; }
    </style>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-blue-400">Tools</p>
            <h1 class="text-2xl font-bold mpc-title mt-1">Marketing Plan Calculator</h1>
            <p class="text-sm mpc-sub mt-1">Build a 12-month channel plan, projections dashboard and Sayzio ROI summary, no spreadsheet needed.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if(($canCreate ?? true))
                @if($latestStrategy ?? null)
                    {{-- Task #6739 — seed a new plan from the latest AI Marketing Strategist plan. --}}
                    <a href="{{ route('user.marketing-plan.create', ['from_strategy' => $latestStrategy->id]) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-500/15 text-blue-300 text-sm font-semibold hover:bg-blue-500/25"
                       title="Pre-fill a new plan from “{{ $latestStrategy->title }}”">
                        <i class="fas fa-wand-magic-sparkles"></i> Start from AI suggestions
                    </a>
                @endif
                {{-- Task #6772 — start a plan from real Sayzio analytics/leads/revenue. --}}
                <a href="{{ route('user.marketing-plan.create', ['use_actuals' => 1]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-500/15 text-blue-300 text-sm font-semibold hover:bg-blue-500/25"
                   title="Pre-fill a new plan from your real link analytics, leads and revenue">
                    <i class="fas fa-bolt"></i> Use my Sayzio data
                </a>
                {{-- Task #6767 — new plan with an optional industry benchmark preset. --}}
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                        <i class="fas fa-plus"></i> New plan <i class="fas fa-chevron-down text-[10px]"></i>
                    </button>
                    <div x-show="open" x-cloak
                         class="mpc-menu absolute right-0 mt-1.5 w-64 rounded-xl shadow-xl z-20 overflow-hidden">
                        <a href="{{ route('user.marketing-plan.create') }}" class="mpc-menu-item block px-4 py-2.5 text-sm">
                            <span class="font-semibold">Generic / custom</span>
                            <span class="block text-[11px] mpc-faint">The default cross-industry benchmarks</span>
                        </a>
                        @foreach(\App\Services\MarketingPlanIndustryPresets::PRESETS as $presetKey => $preset)
                            <a href="{{ route('user.marketing-plan.create', ['preset' => $presetKey]) }}"
                               class="mpc-menu-item block px-4 py-2.5 text-sm" title="{{ $preset['description'] }}">
                                {{ $preset['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- Task #6766 — at the saved-plan cap: create is an upsell, not a link. --}}
                <a href="{{ route('user.upgrade') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white/10 text-white/70 text-sm font-semibold hover:bg-white/15"
                   title="You have reached your plan's saved-plan limit">
                    <i class="fas fa-lock"></i> Plan limit reached. Upgrade
                </a>
            @endif
        </div>
    </div>

    @if(isset($planCap) && $planCap >= 0)
        <p class="text-xs mpc-faint -mt-3 mb-4">
            {{ $plans->count() }} of {{ $planCap }} saved plan{{ $planCap === 1 ? '' : 's' }} used on your plan.
        </p>
    @endif

    @if(session('limit_reached') || !($canCreate ?? true))
        <div class="mb-4 rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 flex items-start gap-3">
            <i class="fas fa-lock text-blue-300 mt-0.5"></i>
            <div class="flex-1 text-sm text-blue-100">
                <div class="font-semibold">You've reached your plan's saved-plan limit.</div>
                <div class="text-blue-200/80 mt-0.5">You can still view, edit and delete your existing plans. Upgrade to create new ones.</div>
            </div>
            <a href="{{ route('user.upgrade') }}"
               class="text-xs font-semibold uppercase tracking-wider px-3 py-1.5 rounded-lg bg-blue-500 hover:bg-blue-400 text-white">Upgrade</a>
        </div>
    @endif

    @if(session('status'))
        <div class="mb-4 rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-2.5 text-sm text-blue-300">{{ session('status') }}</div>
    @endif

    @if($plans->isEmpty())
        <div class="rounded-2xl mpc-card p-10 text-center">
            <i class="fas fa-calculator text-3xl text-blue-400/80"></i>
            <h2 class="text-lg font-semibold mpc-title mt-4">No saved plans yet</h2>
            <p class="text-sm mpc-sub mt-1 max-w-md mx-auto">
                Start from proven channel benchmarks, enter your budget and assumptions, and get a
                monthly plan, results dashboard and value-of-Sayzio summary computed live.
            </p>
            @if(($canCreate ?? true))
                <a href="{{ route('user.marketing-plan.create') }}"
                   class="inline-block mt-5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    Build your first plan
                </a>
            @else
                <a href="{{ route('user.upgrade') }}"
                   class="inline-block mt-5 px-4 py-2 rounded-xl bg-white/10 text-white/70 text-sm font-semibold hover:bg-white/15">
                    <i class="fas fa-lock mr-1"></i> Upgrade to save plans
                </a>
            @endif
        </div>
    @else
        <ul class="space-y-3">
            @foreach($plans as $p)
                <li class="rounded-2xl mpc-card p-4 flex items-center justify-between gap-4">
                    <a href="{{ route('user.marketing-plan.edit', $p->id) }}" class="min-w-0 flex-1 group">
                        <h3 class="mpc-title font-semibold truncate group-hover:text-blue-400 transition">
                            {{ $p->name }}
                            {{-- Task #6767 — the industry preset this plan started from ("Custom" for pre-preset payloads). --}}
                            <span class="inline-block align-middle ml-1.5 px-2 py-0.5 rounded-md bg-blue-500/15 text-blue-400 text-[10px] font-bold uppercase tracking-wide"
                                  data-mpc-preset-badge>{{ \App\Services\MarketingPlanIndustryPresets::label(data_get($p->payload, 'industry_preset')) }}</span>
                        </h3>
                        <p class="text-sm mpc-sub mt-0.5 truncate">
                            {{ trim((string) data_get($p->payload, 'company')) !== '' ? data_get($p->payload, 'company') : 'No company set' }}
                            · Budget ₹{{ number_format((float) data_get($p->payload, 'annual_budget', 0)) }}/yr
                        </p>
                    </a>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[11px] mpc-faint hidden sm:inline">Updated {{ $p->updated_at?->diffForHumans() }}</span>
                        <a href="{{ route('user.marketing-plan.edit', $p->id) }}"
                           class="px-3 py-1.5 rounded-lg bg-blue-600/15 text-blue-400 text-xs font-semibold hover:bg-blue-600/25">Open</a>
                        <form method="POST" action="{{ route('user.marketing-plan.destroy', $p->id) }}"
                              onsubmit="return confirm('Delete this plan? This cannot be undone.');">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-500/10 text-red-400 text-xs font-semibold hover:bg-red-500/20">Delete</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
