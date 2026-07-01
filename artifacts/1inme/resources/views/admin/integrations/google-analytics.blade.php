@extends('admin.layouts.app')
@section('title', 'Google Analytics 4')
@section('page-title', 'Google Analytics 4')

@php
    $toneClass = function (string $tone) {
        return match ($tone) {
            'green' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300',
            'amber' => 'bg-amber-500/10 border-amber-500/20 text-amber-300',
            'red'   => 'bg-red-500/10 border-red-500/20 text-red-300',
            default => 'bg-white/5 border-white/10 text-white/50',
        };
    };
@endphp

@section('content')
<div class="max-w-2xl space-y-6">

    <a href="{{ route('admin.integrations.index') }}" class="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white/70">
        <i class="fas fa-arrow-left"></i> Back to Integrations
    </a>

    <p class="text-sm text-white/50">
        Server-side GA4 event forwarding uses the Measurement Protocol, so no platform-wide API key is needed &mdash;
        each creator supplies their own Measurement ID and API secret when connecting. This switch simply controls
        whether Google Analytics is offered to creators in the Connected Apps area. When off, it shows as
        &ldquo;coming soon&rdquo;.
    </p>

    <form method="POST" action="{{ route('admin.integrations.google-analytics.update') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="glass rounded-2xl border border-white/10 p-6 space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-white flex items-center gap-2">
                        <i class="fas fa-chart-line text-orange-400"></i> GA4 forwarding
                    </h3>
                    <p class="text-xs text-white/40">Availability of Google Analytics in the creator Connected Apps area.</p>
                </div>
                <span class="shrink-0 px-2.5 py-1 rounded-lg border text-[11px] font-medium {{ $toneClass($status['tone']) }}">
                    {{ $status['label'] }}
                </span>
            </div>

            <label class="flex items-center gap-3 text-sm text-white/70">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" class="accent-emerald-500" @checked($enabled)>
                Enable Google Analytics for creators
            </label>
        </div>

        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            <i class="fas fa-save mr-1"></i> Save settings
        </button>
    </form>

</div>
@endsection
