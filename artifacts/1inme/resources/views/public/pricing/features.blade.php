@extends('public.layouts.site')

@section('title', 'Premium features')

@push('head')
<style>
    /* Compare-features matrix — one fixed-width column per plan. Columns use
       locked px widths so every plan lines up; the matrix lives in a
       self-contained scroll box (capped height) so BOTH the feature-name
       column (sticky left) and the plan header row (sticky top) stay anchored
       while you scroll horizontally and vertically through the long feature
       list. Mirrors the .feat-* matrix on the /pricing page. */
    .feat-matrix-scroll {
        overflow: auto; -webkit-overflow-scrolling: touch;
        max-height: min(80vh, 820px);
        scrollbar-width: thin; scrollbar-color: rgba(61,107,255,.55) transparent;
    }
    .feat-matrix-scroll::-webkit-scrollbar { width: 9px; height: 9px; }
    .feat-matrix-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.03); }
    .feat-matrix-scroll::-webkit-scrollbar-thumb { background: rgba(61,107,255,.5); border-radius: 9999px; }
    .feat-matrix-scroll::-webkit-scrollbar-thumb:hover { background: rgba(61,107,255,.75); }
    .feat-matrix { min-width: max-content; }
    .feat-cell {
        padding: .85rem 1rem; border-top: 1px solid rgba(255,255,255,.05);
        font-size: .82rem; color: #cbd5e1;
        display: flex; align-items: center;
        transition: background-color .18s ease;
    }
    .feat-cell.feat-stripe { background: rgba(255,255,255,.022); }
    .feat-cell.text-center { justify-content: center; text-align: center; }
    .feat-cell.feat-head {
        border-top: 0;
        background: rgba(17,16,28,.92); backdrop-filter: blur(8px);
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .68rem; font-weight: 700; color: #94a3b8;
        padding-top: 1rem; padding-bottom: 1rem;
        position: sticky; top: 0; z-index: 3;
        box-shadow: inset 0 -1px 0 rgba(144,172,255,.22);
    }
    .feat-cell.feat-row-name {
        position: sticky; left: 0; background: #0b0a14;
        z-index: 2; color: #e5e7eb; font-weight: 500;
        flex-direction: column; align-items: flex-start; justify-content: center;
        box-shadow: inset -1px 0 0 rgba(255,255,255,.04);
    }
    .feat-cell.feat-row-name .feat-row-desc {
        font-size: .7rem; font-weight: 400; color: #8b94a7;
        line-height: 1.35; margin-top: .15rem; text-transform: none; letter-spacing: 0;
    }
    .feat-cell.feat-row-name .feat-row-unit {
        font-size: .62rem; font-weight: 700; color: #7c86c9;
        text-transform: uppercase; letter-spacing: .06em; margin-top: .25rem;
    }
    .feat-cell.feat-row-name.feat-stripe { background: #0d0c17; }
    .feat-cell.feat-head.feat-row-name { z-index: 4; background: rgba(17,16,28,.96); }
    .feat-cell.feat-group {
        background: linear-gradient(90deg, rgba(61,107,255,.18), rgba(61,107,255,.08));
        color: #bccfff;
        text-transform: uppercase; letter-spacing: .08em;
        font-size: .66rem; font-weight: 700;
        padding: .6rem 1rem;
        position: sticky; left: 0; z-index: 1;
        box-shadow: inset 2px 0 0 rgba(144,172,255,.55);
    }
    .feat-cell.feat-popular-col {
        background: rgba(61,107,255,.07);
        box-shadow: inset 1px 0 0 rgba(144,172,255,.12), inset -1px 0 0 rgba(144,172,255,.12);
    }
    .feat-cell.feat-popular-col.feat-stripe { background: rgba(61,107,255,.10); }
    .feat-cell.feat-head.feat-popular-col {
        background: rgba(61,107,255,.20);
        box-shadow: inset 0 -1px 0 rgba(144,172,255,.45), inset 1px 0 0 rgba(144,172,255,.3), inset -1px 0 0 rgba(144,172,255,.3);
    }
    .feat-mark { display: inline-flex; align-items: center; justify-content: center;
                 width: 28px; height: 28px; border-radius: 9999px; }
    .feat-mark-yes {
        background: rgba(16,185,129,.18); color: #34d399;
        box-shadow: 0 0 0 1px rgba(52,211,153,.35), 0 4px 12px -6px rgba(16,185,129,.6);
    }
    .feat-mark-no  { background: rgba(148,163,184,.08); color: #5b647a; }

    /* Light-mode overrides (dark is the default). The feat-* rules use
       hard-coded dark values, so they need explicit light-mode counterparts. */
    html.light-mode .feat-cell { border-top-color: rgba(15,23,42,.08); color: #475569; }
    html.light-mode .feat-cell.feat-head { background: #f1f0f7; color: #64748b; }
    html.light-mode .feat-cell.feat-row-name { background: #ffffff; color: #1e293b; }
    html.light-mode .feat-cell.feat-row-name .feat-row-desc { color: #64748b; }
    html.light-mode .feat-cell.feat-head.feat-row-name { background: #f1f0f7; }
    html.light-mode .feat-cell.feat-popular-col { background: rgba(61,107,255,.06); }
    html.light-mode .feat-cell.feat-head.feat-popular-col { background: #e6e0fb; }
    html.light-mode .feat-mark-no { color: #94a3b8; }
    html.light-mode .feat-cell.feat-stripe { background: rgba(15,23,42,.028); }
    html.light-mode .feat-cell.feat-row-name.feat-stripe { background: #f7f7fb; }
    html.light-mode .feat-cell.feat-popular-col.feat-stripe { background: rgba(61,107,255,.09); }
    html.light-mode .feat-cell.feat-group {
        background: linear-gradient(90deg, #e7e2fb, #f1eefc); color: #2342c7;
    }
    html.light-mode .feat-mark-yes { box-shadow: 0 0 0 1px rgba(16,185,129,.3), 0 4px 12px -6px rgba(16,185,129,.4); }
</style>
@endpush

@section('content')
@php
    $markNo  = '<span class="feat-mark feat-mark-no" aria-label="Not included"><i class="fas fa-minus text-[10px]"></i></span>';
    $markYes = '<span class="feat-mark feat-mark-yes" aria-label="Included"><i class="fas fa-check text-[11px]"></i></span>';
    // Render one plan's value for a catalogue entry, reusing the shared
    // resolver so number / Unlimited / included / excluded rendering lives in
    // exactly one place (PremiumFeatures::resolveCell()).
    $renderCell = function ($plan, $entry) use ($markNo, $markYes) {
        $c = \App\Modules\Common\Support\PremiumFeatures::resolveCell($plan, $entry);
        if ($c['kind'] === 'number') {
            if ($c['unlimited']) return '<span class="text-emerald-300 font-semibold">Unlimited</span>';
            if (!$c['on']) return $markNo;
            return '<span class="text-white font-semibold">' . e($c['text']) . '</span>';
        }
        if ($c['kind'] === 'analytics') {
            $cls = $c['on'] ? 'text-emerald-300 font-semibold' : 'text-gray-300';
            return '<span class="' . $cls . '">' . e($c['text']) . '</span>';
        }
        return $c['on'] ? $markYes : $markNo;
    };
    $colTpl = '260px repeat(' . max(count($plans), 1) . ', 150px)';
@endphp
<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-3">
            <div class="text-xs font-bold uppercase tracking-[.2em] text-blue-400">Premium features</div>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">Everything that unlocks on a paid plan.</h1>
            <p class="text-lg text-gray-400">Plain-English explanations of every premium capability — and exactly which plan includes it.</p>
        </div>

        @if(count($plans) === 0)
            <div class="mt-14 text-center text-gray-400">No plans are currently published.</div>
        @else
        <div class="mt-12 rounded-3xl border border-white/10 bg-white/[0.02] overflow-hidden">
            <div class="feat-matrix-scroll">
                <div class="feat-matrix grid" style="grid-template-columns: {{ $colTpl }};">
                    {{-- Header row --}}
                    <div class="feat-cell feat-head feat-row-name">Feature</div>
                    @foreach($plans as $p)
                        <div class="feat-cell feat-head text-center {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                            <span class="text-white text-sm font-semibold normal-case tracking-normal">
                                @if($p->is_popular)<i class="fas fa-star text-pink-400 text-[10px]"></i>@endif
                                {{ $p->name }}
                            </span>
                        </div>
                    @endforeach

                    {{-- Grouped feature rows --}}
                    @foreach($grouped as $groupName => $items)
                        <div class="feat-cell feat-group" style="grid-column: span {{ count($plans) + 1 }};">{{ $groupName }}</div>
                        @foreach($items as $ri => $item)
                            @php $stripe = $ri % 2 === 1 ? 'feat-stripe' : ''; @endphp
                            <div class="feat-cell feat-row-name {{ $stripe }}">
                                <span class="text-white font-semibold leading-snug">{{ $item['name'] }}</span>
                                <span class="feat-row-desc">{{ $item['description'] }}</span>
                                @if(!empty($item['unit']))
                                    <span class="feat-row-unit">{{ $item['unit'] }}</span>
                                @endif
                            </div>
                            @foreach($plans as $p)
                                <div class="feat-cell text-center {{ $stripe }} {{ $p->is_popular ? 'feat-popular-col' : '' }}">
                                    {!! $renderCell($p, $item) !!}
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
        @endif

        <div class="mt-14 text-center">
            <div class="flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('site.pricing') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-blue-600 text-white hover:bg-blue-700 text-sm font-bold">
                    <i class="fas fa-tags"></i> See pricing plans
                </a>
                <a href="{{ route('site.pricing', ['view' => 'coins']) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full border border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08] text-sm font-medium">
                    <i class="fas fa-coins text-amber-400"></i> Coin packages
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
