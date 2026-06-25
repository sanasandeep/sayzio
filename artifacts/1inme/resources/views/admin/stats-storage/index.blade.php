@extends('admin.layouts.app')
@section('title', 'Analytics Storage')
@section('page-title', 'Analytics Storage')

@php
    use Illuminate\Support\Carbon;

    $effectiveLabel = $health['effective_days'] === null
        ? 'No automatic pruning'
        : $health['effective_days'] . ' ' . \Illuminate\Support\Str::plural('day', $health['effective_days']);

    $planLabel = $health['plan_retention'] === -1
        ? 'Unlimited (keep forever)'
        : $health['plan_retention'] . ' ' . \Illuminate\Support\Str::plural('day', $health['plan_retention']);

    $lastRun = is_array($health['last_run'] ?? null) ? $health['last_run'] : null;
@endphp

@section('content')
<div class="max-w-3xl space-y-6">

    <p class="text-sm text-white/50">
        How fast the high-volume analytics tables (link clicks and page sessions) are growing, the
        retention window the nightly cleanup applies, and the outcome of the last sweep. Set a
        <strong>hard cap</strong> to bound storage even when a plan keeps history forever, and a
        <strong>growth alert threshold</strong> to be warned before a table grows unchecked.
    </p>

    @if (session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @unless ($health['available'])
        <div class="p-3 rounded-xl bg-white/5 border border-white/10 text-white/50 text-xs">
            The analytics tables aren't present in this environment yet, so there's nothing to report.
        </div>
    @endunless

    {{-- Unbounded-growth warning --}}
    @if ($health['growth_unbounded'])
        <div class="rounded-2xl p-5 border" style="border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
            <div class="flex items-start gap-4">
                <div class="w-11 h-11 shrink-0 bg-amber-500/15 rounded-xl flex items-center justify-center">
                    <i class="fas fa-triangle-exclamation text-amber-400 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-amber-300">Analytics storage is growing unbounded</h2>
                    <p class="text-sm text-white/70 mt-1">
                        A table has crossed the alert threshold of
                        <span class="font-mono">{{ number_format($health['alert_threshold']) }}</span> rows and nothing
                        will prune it &mdash; {{ $health['reason'] }}. Set a hard cap below to bound storage.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Retention summary --}}
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="glass rounded-2xl border border-white/10 p-4">
            <p class="text-xs uppercase tracking-wider text-white/40">Effective window</p>
            <p class="text-lg font-semibold text-white mt-1">{{ $effectiveLabel }}</p>
            <p class="text-[11px] text-white/40 mt-1">{{ $health['reason'] }}</p>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-4">
            <p class="text-xs uppercase tracking-wider text-white/40">Plan retention</p>
            <p class="text-lg font-semibold text-white mt-1">{{ $planLabel }}</p>
            <p class="text-[11px] text-white/40 mt-1">Largest <span class="font-mono">stats_retention_days</span> across active plans</p>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-4">
            <p class="text-xs uppercase tracking-wider text-white/40">Hard cap</p>
            <p class="text-lg font-semibold text-white mt-1">
                @if ($health['hard_max_days'] === null)
                    <span class="text-white/40">Not set</span>
                @else
                    {{ $health['hard_max_days'] }} {{ \Illuminate\Support\Str::plural('day', $health['hard_max_days']) }}
                @endif
            </p>
            <p class="text-[11px] text-white/40 mt-1"><span class="font-mono">stats.hard_max_days</span></p>
        </div>
    </div>

    {{-- Per-table sizes --}}
    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="p-5 border-b border-white/10">
            <h3 class="font-semibold text-white flex items-center gap-2">
                <i class="fas fa-database text-sky-400"></i> Table sizes (estimated)
            </h3>
            <p class="text-xs text-white/40 mt-1">From planner statistics &mdash; fast, approximate, never a full count.</p>
        </div>
        <table class="w-full">
            <thead class="bg-white/5">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-medium text-white/40 uppercase">Table</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-white/40 uppercase">Estimated rows</th>
                    <th class="px-5 py-3 text-right text-xs font-medium text-white/40 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse ($health['tables'] as $name => $t)
                    <tr>
                        <td class="px-5 py-3 text-sm text-white font-mono">{{ $name }}</td>
                        <td class="px-5 py-3 text-sm text-white/80 text-right">{{ number_format($t['estimated_rows']) }}</td>
                        <td class="px-5 py-3 text-right">
                            @if ($t['over_threshold'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/10 text-amber-300">
                                    <i class="fas fa-triangle-exclamation"></i> Over threshold
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-300">
                                    <i class="fas fa-check"></i> OK
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-6 text-center text-white/30 text-sm">No analytics tables found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Last sweep outcome --}}
    <div class="glass rounded-2xl border border-white/10 p-5 space-y-3">
        <h3 class="font-semibold text-white flex items-center gap-2">
            <i class="fas fa-broom text-violet-400"></i> Last cleanup sweep
        </h3>
        @if ($lastRun === null)
            <p class="text-sm text-white/40">No sweep has run yet. <span class="font-mono">stats:prune-history</span> runs daily at 04:05.</p>
        @else
            <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                <span class="text-white/60">
                    Ran
                    <span class="text-white" title="{{ $lastRun['ran_at'] ?? '' }}">
                        {{ isset($lastRun['ran_at']) ? Carbon::parse($lastRun['ran_at'])->diffForHumans() : 'unknown' }}
                    </span>
                </span>
                @php($action = $lastRun['action'] ?? 'unknown')
                <span class="text-white/60">
                    Outcome:
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $action === 'pruned' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-white/10 text-white/60' }}">
                        {{ $action === 'pruned' ? 'Pruned' : ($action === 'noop' ? 'No deletions' : ucfirst($action)) }}
                    </span>
                </span>
                @if (!empty($lastRun['dry_run']))
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-sky-500/10 text-sky-300">Dry run</span>
                @endif
            </div>
            <p class="text-xs text-white/40">{{ $lastRun['reason'] ?? '' }}</p>
            @if (!empty($lastRun['tables']) && is_array($lastRun['tables']))
                <ul class="text-xs text-white/50 font-mono space-y-1 pt-1">
                    @foreach ($lastRun['tables'] as $tName => $tInfo)
                        <li>
                            {{ $tName }} &mdash;
                            {{ number_format((int) ($tInfo['rows_deleted'] ?? 0)) }} row(s) deleted@if (!empty($tInfo['dropped_partitions'])), {{ count($tInfo['dropped_partitions']) }} partition(s) dropped@endif.
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>

    {{-- Settings form --}}
    <form method="POST" action="{{ route('admin.stats-storage.update') }}" class="glass rounded-2xl border border-white/10 p-6 space-y-6">
        @csrf @method('PUT')

        <div>
            <h3 class="font-semibold text-white flex items-center gap-2">
                <i class="fas fa-sliders text-amber-400"></i> Storage limits
            </h3>
            <p class="text-xs text-white/40 mt-1">Leave a field blank to keep its current value; tick "Clear" to remove it.</p>
        </div>

        {{-- Hard cap --}}
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Hard cap (days)</label>
            <input type="number" name="hard_max_days" min="1" max="36500"
                   value="{{ old('hard_max_days') }}"
                   placeholder="{{ $health['hard_max_days'] !== null ? $health['hard_max_days'] : 'not set' }}"
                   class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
            <p class="text-[11px] text-white/30 mt-1">
                Deletes click/session rows older than this many days regardless of plan retention &mdash; the safety net
                that bounds storage even when a plan keeps history forever.
            </p>
            @if ($health['hard_max_days'] !== null)
                <label class="inline-flex items-center gap-2 mt-2 text-xs text-white/50">
                    <input type="checkbox" name="clear_hard_max_days" value="1" class="rounded border-white/20 bg-white/5">
                    Clear the hard cap (revert to plan-only retention)
                </label>
            @endif
        </div>

        {{-- Alert threshold --}}
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40 mb-1 block">Growth alert threshold (rows)</label>
            <input type="number" name="alert_row_threshold" min="1"
                   value="{{ old('alert_row_threshold') }}"
                   placeholder="{{ number_format($health['alert_threshold']) }}"
                   class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white">
            <p class="text-[11px] text-white/30 mt-1">
                When a table's estimated rows cross this and nothing will prune it, the nightly sweep raises an admin
                alert. Default {{ number_format(\App\Modules\Common\Support\StatsRetentionPolicy::DEFAULT_ALERT_THRESHOLD) }}.
            </p>
            <label class="inline-flex items-center gap-2 mt-2 text-xs text-white/50">
                <input type="checkbox" name="clear_alert_row_threshold" value="1" class="rounded border-white/20 bg-white/5">
                Reset to the default threshold
            </label>
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold bg-violet-500/20 hover:bg-violet-500/30 text-violet-200 border border-violet-500/40 transition">
                <i class="fas fa-floppy-disk"></i> Save settings
            </button>
        </div>
    </form>

</div>
@endsection
