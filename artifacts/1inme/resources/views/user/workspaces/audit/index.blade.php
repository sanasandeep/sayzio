@extends('user.layouts.app')

@section('title', 'Workspace audit log')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">
                {{ $workspace->name }} — Sensitive action log
            </h1>
            <p class="text-sm opacity-70 mt-1">
                Append-only record of high-risk actions on this workspace.
                Older rows can't be edited or removed.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.workspaces.audit.preferences') }}"
               class="px-3 py-2 rounded-lg text-sm font-semibold border glass-hover"
               style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-bell mr-1"></i> Alert preferences
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-4 px-4 py-3 rounded-lg border text-sm flex items-center gap-3
                {{ $chain['ok'] ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-red-500/10 border-red-500/30 text-red-300' }}">
        @if($chain['ok'])
            <i class="fas fa-link"></i>
            <span>Hash chain intact — {{ $chain['count'] }} {{ $chain['count'] === 1 ? 'event' : 'events' }} verified.</span>
        @else
            <i class="fas fa-triangle-exclamation"></i>
            <span>Hash chain mismatch detected at event #{{ $chain['broken_at'] }}. Contact support immediately.</span>
        @endif
    </div>

    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5">
        <input type="text" name="q" value="{{ $filters['q'] }}"
               placeholder="Search target or IP…"
               class="px-3 py-2 rounded-lg border text-sm bg-transparent"
               style="border-color: var(--border-strong); color: var(--text-primary);">

        <select name="action" class="px-3 py-2 rounded-lg border text-sm bg-transparent"
                style="border-color: var(--border-strong); color: var(--text-primary);">
            <option value="">All actions</option>
            @foreach($catalog as $slug => $meta)
                <option value="{{ $slug }}" @selected($filters['action'] === $slug)>{{ $meta['label'] }}</option>
            @endforeach
        </select>

        <select name="flagged" class="px-3 py-2 rounded-lg border text-sm bg-transparent"
                style="border-color: var(--border-strong); color: var(--text-primary);">
            <option value="">Any status</option>
            <option value="1" @selected($filters['flagged'] === '1')>Flagged "wasn't me"</option>
        </select>

        <button type="submit"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-semibold hover:bg-primary-700">
            <i class="fas fa-filter mr-1"></i> Filter
        </button>
    </form>

    <div class="rounded-xl border border-white/10 overflow-hidden" style="background: var(--bg-card);">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-xs uppercase tracking-wide" style="color: var(--text-faint);">
                <tr>
                    <th class="px-4 py-3 text-left">When</th>
                    <th class="px-4 py-3 text-left">Actor</th>
                    <th class="px-4 py-3 text-left">Action</th>
                    <th class="px-4 py-3 text-left">Target</th>
                    <th class="px-4 py-3 text-left">IP</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($events as $event)
                    <tr class="{{ $event->reported_unauthorized_at ? 'bg-red-500/5' : '' }}">
                        <td class="px-4 py-2 text-xs" style="color: var(--text-faint);">
                            {{ $event->occurred_at?->toDateTimeString() }}
                        </td>
                        <td class="px-4 py-2">{{ $event->actor?->name ?? $event->actor?->email ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="px-2 py-0.5 text-[10px] rounded bg-white/10 uppercase tracking-wide">
                                {{ $catalog[$event->action]['label'] ?? $event->action }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            <div class="text-sm">{{ $event->target_label ?: '—' }}</div>
                            @if($event->target_type)
                                <div class="text-[11px]" style="color: var(--text-muted);">{{ $event->target_type }}{{ $event->target_id ? ' #'.$event->target_id : '' }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs font-mono" style="color: var(--text-muted);">{{ $event->ip ?: '—' }}</td>
                        <td class="px-4 py-2">
                            @if($event->reported_unauthorized_at)
                                <span class="px-2 py-0.5 text-[10px] rounded bg-red-500/20 text-red-300 uppercase">
                                    Flagged
                                </span>
                            @else
                                <span class="px-2 py-0.5 text-[10px] rounded bg-emerald-500/10 text-emerald-300 uppercase">
                                    OK
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('user.workspaces.audit.report.show', ['event' => $event->id, 'recipient' => auth()->id()], now()->addHours(2)) }}"
                               class="text-xs text-primary-400 hover:text-primary-200 font-semibold">
                                Investigate <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center" style="color: var(--text-faint);">
                            No sensitive actions recorded yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $events->links() }}</div>
</div>
@endsection
