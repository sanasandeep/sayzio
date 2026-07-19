@extends('admin.layouts.app')
@section('title', 'Activity Log')
@section('page-title', 'Activity Log')

@section('content')
@php
    $hasFilter = collect($filters)->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
    $exportParams = array_filter($filters, fn ($v) => $v !== '' && $v !== null);
@endphp
<div class="glass rounded-2xl border border-white/10 p-6">
    <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
        <div>
            <h3 class="text-white font-semibold">User-management activity</h3>
            <p class="text-xs text-white/40">Plan assignments, coin grants/deductions, account creation, suspensions and reactivations.</p>
        </div>
        <a href="{{ route('admin.users.activity-log.export', $exportParams) }}"
           class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70 whitespace-nowrap">
            <i class="fas fa-file-csv mr-1"></i> Export CSV
        </a>
    </div>

    <form method="GET" action="{{ route('admin.users.activity-log.index') }}"
          class="rounded-xl border border-white/5 bg-white/[0.02] p-3 mb-4 space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Operator</span>
                <input type="text" name="operator" value="{{ $filters['operator'] }}" placeholder="Name or email"
                       class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Target user</span>
                <input type="text" name="target" value="{{ $filters['target'] }}" placeholder="Name, email, or id"
                       class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Action</span>
                <select name="action"
                        class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                    <option value="">Any action</option>
                    @foreach($actions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">From</span>
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
            </label>
            <label class="block">
                <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">To</span>
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
            </label>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="submit"
                    class="px-3 py-1.5 rounded-lg bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 text-xs font-medium">
                <i class="fas fa-filter mr-1"></i> Apply filters
            </button>
            @if($hasFilter)
                <a href="{{ route('admin.users.activity-log.index') }}"
                   class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/60 text-xs">Clear filters</a>
            @endif
        </div>
    </form>

    @if($audits->isEmpty())
        <p class="text-sm text-white/40">
            {{ $hasFilter ? 'No entries match this filter.' : 'No activity recorded yet.' }}
        </p>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">When</th>
                <th class="text-left">Operator</th>
                <th class="text-left">Action</th>
                <th class="text-left">Target</th>
                <th class="text-left">Details</th>
                <th class="text-left">IP</th>
            </tr></thead>
            <tbody>
            @foreach($audits as $a)
                <tr class="border-t border-white/5 align-top">
                    <td class="py-2 text-white/60 whitespace-nowrap" title="{{ $a->created_at?->toDateTimeString() }}">
                        {{ $a->created_at?->diffForHumans() }}
                    </td>
                    <td class="text-white/80">
                        {{ $a->admin_name ?: 'System' }}
                        @if($a->admin_email)<span class="block text-xs text-white/30">{{ $a->admin_email }}</span>@endif
                    </td>
                    <td>
                        <span class="px-2 py-0.5 rounded-md text-xs
                            @switch($a->action)
                                @case('coins.granted') bg-emerald-500/10 text-emerald-300 @break
                                @case('coins.deducted') bg-rose-500/10 text-rose-300 @break
                                @case('account.suspended') bg-rose-500/10 text-rose-300 @break
                                @case('account.reactivated') bg-emerald-500/10 text-emerald-300 @break
                                @case('account.created') bg-sky-500/10 text-sky-300 @break
                                @default bg-blue-500/10 text-blue-300
                            @endswitch">
                            {{ $a->actionLabel() }}
                        </span>
                    </td>
                    <td class="text-white/80">
                        @if($a->target_user_id)
                            <a href="{{ route('admin.users.show', $a->target_user_id) }}" class="hover:text-blue-300">
                                {{ $a->target_name ?: ('#' . $a->target_user_id) }}
                            </a>
                            @if($a->target_email)<span class="block text-xs text-white/30">{{ $a->target_email }}</span>@endif
                        @else
                            <span class="text-white/30">-</span>
                        @endif
                    </td>
                    <td class="text-white/50 text-xs max-w-xs">
                        @if(!empty($a->details))
                            <code class="break-words">{{ \Illuminate\Support\Str::limit(json_encode($a->details), 160) }}</code>
                        @else
 -
                        @endif
                    </td>
                    <td class="text-white/30 text-xs whitespace-nowrap">{{ $a->ip ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($audits->hasPages())
        <div class="mt-4">{{ $audits->links() }}</div>
    @endif
    @endif
</div>
@endsection
