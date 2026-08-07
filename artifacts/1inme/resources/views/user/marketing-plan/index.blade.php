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
    </style>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.15em] text-blue-400">Tools</p>
            <h1 class="text-2xl font-bold mpc-title mt-1">Marketing Plan Calculator</h1>
            <p class="text-sm mpc-sub mt-1">Build a 12-month channel plan, projections dashboard and Sayzio ROI summary — no spreadsheet needed.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if($latestStrategy ?? null)
                {{-- Task #6739 — seed a new plan from the latest AI Marketing Strategist plan. --}}
                <a href="{{ route('user.marketing-plan.create', ['from_strategy' => $latestStrategy->id]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-500/15 text-blue-300 text-sm font-semibold hover:bg-blue-500/25"
                   title="Pre-fill a new plan from “{{ $latestStrategy->title }}”">
                    <i class="fas fa-wand-magic-sparkles"></i> Start from AI suggestions
                </a>
            @endif
            <a href="{{ route('user.marketing-plan.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                <i class="fas fa-plus"></i> New plan
            </a>
        </div>
    </div>

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
            <a href="{{ route('user.marketing-plan.create') }}"
               class="inline-block mt-5 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                Build your first plan
            </a>
        </div>
    @else
        <ul class="space-y-3">
            @foreach($plans as $p)
                <li class="rounded-2xl mpc-card p-4 flex items-center justify-between gap-4">
                    <a href="{{ route('user.marketing-plan.edit', $p->id) }}" class="min-w-0 flex-1 group">
                        <h3 class="mpc-title font-semibold truncate group-hover:text-blue-400 transition">{{ $p->name }}</h3>
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
