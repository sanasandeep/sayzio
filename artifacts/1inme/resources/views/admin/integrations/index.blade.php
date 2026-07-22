@extends('admin.layouts.app')
@section('title', 'Integrations')
@section('page-title', 'Integrations')

@push('styles')
<style>
html.light-mode .admin-int-stat-green { color: #065f46; }
html.light-mode .admin-int-stat-amber { color: #92400e; }
html.light-mode .admin-int-open       { color: #1d4ed8; }
</style>
@endpush

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'ak-tone-green bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'ak-tone-amber bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'ak-tone-red bg-red-500/10 border-red-500/20 text-red-300',
            default => 'ak-tone-neutral bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@section('content')
<div class="space-y-6">

    <p class="ak-muted text-sm text-white/50 max-w-3xl">
        Every third-party credential the platform talks to, in one place. Secrets are encrypted at rest,
        masked in the UI, and each integration falls back to the server environment until you save a value
        here &mdash; so changes take effect without a redeploy. Statuses below reflect the effective
        configuration (admin value or env fallback).
    </p>

    {{-- Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-w-3xl">
        <div class="glass rounded-xl p-4 border border-white/10">
            <div class="ak-note text-[11px] uppercase tracking-wider text-white/40 flex items-center gap-2"><i class="fas fa-layer-group"></i> Integrations</div>
            <div class="ak-strong text-2xl font-bold text-white mt-1">{{ $summary['total'] }}</div>
        </div>
        <div class="glass rounded-xl p-4 border border-emerald-500/20">
            <div class="ak-green admin-int-stat-green text-[11px] uppercase tracking-wider text-emerald-300/70 flex items-center gap-2"><i class="fas fa-check-circle"></i> Configured</div>
            <div class="ak-green text-2xl font-bold text-emerald-300 mt-1">{{ $summary['configured'] }}</div>
        </div>
        <div class="glass rounded-xl p-4 border border-amber-500/20">
            <div class="ak-amber admin-int-stat-amber text-[11px] uppercase tracking-wider text-amber-300/70 flex items-center gap-2"><i class="fas fa-triangle-exclamation"></i> Needs attention</div>
            <div class="ak-amber text-2xl font-bold text-amber-300 mt-1">{{ $summary['attention'] }}</div>
        </div>
    </div>

    @foreach($categories as $cat)
        <div class="space-y-3">
            <h2 class="ak-strong text-sm font-semibold text-white/80 flex items-center gap-2">
                <i class="ak-note {{ $cat['icon'] }} text-white/40"></i> {{ $cat['label'] }}
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($cat['items'] as $item)
                    <a href="{{ $item['route'] }}"
                       class="group glass rounded-2xl border {{ ($item['status']['tone'] ?? 'slate') === 'green' ? 'border-emerald-500/20' : (($item['status']['tone'] ?? 'slate') === 'amber' ? 'border-amber-500/20' : 'border-white/10') }} p-5 hover:bg-white/[0.03] transition flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="ak-strong w-10 h-10 rounded-xl flex items-center justify-center text-white/80 bg-white/5 border border-white/10 shrink-0">
                                    <i class="{{ $item['icon'] }}"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="ak-strong text-sm font-semibold text-white truncate">{{ $item['label'] }}</div>
                                    @if($item['external'])
                                        <div class="ak-note text-[10px] uppercase tracking-wider text-white/30 mt-0.5 flex items-center gap-1">
                                            <i class="fas fa-arrow-up-right-from-square"></i> Dedicated editor
                                        </div>
                                    @else
                                        <div class="ak-note text-[10px] uppercase tracking-wider text-white/30 mt-0.5 flex items-center gap-1">
                                            <i class="fas fa-sliders"></i> Managed here
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($item['status']['tone'] ?? 'slate') }}">
                                {{ $item['status']['label'] ?? 'Unknown' }}
                            </span>
                        </div>
                        <p class="ak-muted text-xs text-white/45 leading-relaxed">{{ $item['desc'] }}</p>
                        <div class="ak-blue admin-int-open text-[11px] text-blue-300/80 mt-auto flex items-center gap-1 group-hover:gap-2 transition-all">
                            Open <i class="fas fa-chevron-right text-[9px]"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

</div>
@endsection
