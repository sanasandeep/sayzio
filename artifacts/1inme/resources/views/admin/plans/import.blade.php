@extends('admin.layouts.app')
@section('title', 'Import Plans')
@section('page-title', 'Import Plan Changes')

@section('content')
<div class="max-w-5xl">
    <div class="mb-6">
        <a href="{{ route('admin.plans.index') }}" class="text-sm text-white/50 hover:text-white/80 transition">
            <i class="fas fa-arrow-left mr-1.5"></i>Back to plans
        </a>
    </div>

    {{-- Summary --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <span class="px-3 py-1.5 rounded-lg text-sm font-medium bg-emerald-500/10 border border-emerald-500/30 text-emerald-200">
            {{ $changedCount }} to update
        </span>
        <span class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white/5 border border-white/10 text-white/60">
            {{ $unchangedCount }} unchanged
        </span>
        @if($errorCount > 0)
        <span class="px-3 py-1.5 rounded-lg text-sm font-medium bg-rose-500/10 border border-rose-500/30 text-rose-200">
            {{ $errorCount }} with errors
        </span>
        @endif
        @if($unknownCount > 0)
        <span class="px-3 py-1.5 rounded-lg text-sm font-medium bg-amber-500/10 border border-amber-500/30 text-amber-200">
            {{ $unknownCount }} unknown slug{{ $unknownCount === 1 ? '' : 's' }}
        </span>
        @endif
    </div>

    @if(!empty($unknownColumns))
    <div class="rounded-xl px-4 py-3 mb-5 bg-amber-500/10 border border-amber-500/30 text-amber-100 text-sm">
        <i class="fas fa-exclamation-triangle mr-1.5"></i>
        Unrecognised column{{ count($unknownColumns) === 1 ? '' : 's' }} ignored:
        <span class="font-mono">{{ implode(', ', $unknownColumns) }}</span>
    </div>
    @endif

    {{-- Per-row diff --}}
    <div class="space-y-3">
        @foreach($rows as $row)
        <div class="glass rounded-2xl border p-5
            @if($row['status'] === 'update') border-emerald-500/25
            @elseif($row['status'] === 'error') border-rose-500/30
            @elseif($row['status'] === 'unknown') border-amber-500/30
            @else border-white/10 @endif">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <span class="text-white/90 font-semibold">{{ $row['name'] ?: '—' }}</span>
                    <span class="text-white/40 text-sm font-mono ml-2">{{ $row['slug'] }}</span>
                </div>
                <div class="shrink-0">
                    @if($row['status'] === 'update')
                        <span class="text-xs font-medium text-emerald-300"><i class="fas fa-pen mr-1"></i>{{ count($row['changes']) }} change{{ count($row['changes']) === 1 ? '' : 's' }}</span>
                    @elseif($row['status'] === 'error')
                        <span class="text-xs font-medium text-rose-300"><i class="fas fa-circle-xmark mr-1"></i>Skipped (errors)</span>
                    @elseif($row['status'] === 'unknown')
                        <span class="text-xs font-medium text-amber-300"><i class="fas fa-question-circle mr-1"></i>Skipped (no match)</span>
                    @else
                        <span class="text-xs font-medium text-white/40"><i class="fas fa-check mr-1"></i>No changes</span>
                    @endif
                </div>
            </div>

            @if(!empty($row['errors']))
            <ul class="mt-3 space-y-1 text-sm text-rose-200 list-disc list-inside">
                @foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            @endif

            @if(!empty($row['changes']))
            <div class="mt-3 divide-y divide-white/5 border-t border-white/5">
                @foreach($row['changes'] as $change)
                <div class="flex items-center gap-3 py-2 text-sm">
                    <span class="w-56 shrink-0 text-white/60">{{ $change['label'] }}</span>
                    <span class="text-rose-300/80 line-through font-mono">{{ $change['old'] }}</span>
                    <i class="fas fa-arrow-right text-white/30 text-xs"></i>
                    <span class="text-emerald-300 font-mono">{{ $change['new'] }}</span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Confirm / cancel --}}
    <div class="flex items-center gap-3 mt-8 sticky bottom-0 py-4 bg-gradient-to-t from-black/40 to-transparent">
        @if($changedCount > 0)
        <form method="POST" action="{{ route('admin.plans.import.commit') }}">
            @csrf
            <input type="hidden" name="csv" value="{{ $rawCsv }}">
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition">
                <i class="fas fa-check mr-2"></i>Apply {{ $changedCount }} update{{ $changedCount === 1 ? '' : 's' }}
            </button>
        </form>
        @else
        <span class="text-sm text-white/50">Nothing to apply — no rows changed a value.</span>
        @endif
        <a href="{{ route('admin.plans.index') }}" class="text-sm text-white/60 hover:text-white transition">Cancel</a>
    </div>
</div>
@endsection
