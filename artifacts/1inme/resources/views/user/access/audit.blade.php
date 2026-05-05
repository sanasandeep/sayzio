@extends('user.layouts.app')
@section('title', 'Role change audit log')

@section('content')
@php
    $hasAnyFilter = collect($filters)->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
@endphp
<div class="max-w-6xl mx-auto p-4 lg:p-6 space-y-5">
    <header class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-white">Role change audit log</h1>
                <p class="text-sm text-white/50">
                    Every grant and revoke against a user-pool role, from
                    both the self-service "User access" page and the
                    back-office admin user-detail page. Use the filters
                    below to narrow down a security review and the
                    "Export CSV" button to download the same view.
                </p>
            </div>
            <a href="{{ route($backRoute) }}"
               class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                <i class="fas fa-arrow-left mr-1"></i> Back to User access
            </a>
        </div>
    </header>

    <form method="GET" action="{{ route('user.access.audit.index') }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Actor</span>
                <input type="text" name="actor" value="{{ $filters['actor'] }}"
                       placeholder="Name, email, or id"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Target user</span>
                <input type="text" name="target" value="{{ $filters['target'] }}"
                       placeholder="Name, email, or id"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Role</span>
                <select name="role"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    <option value="">Any role</option>
                    @foreach($roleSlugs as $slug)
                        <option value="{{ $slug }}" @selected($filters['role'] === $slug)>{{ $slug }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Action</span>
                <select name="action"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    <option value="">Any action</option>
                    @foreach($actions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Source</span>
                <select name="source"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                    <option value="">Any source</option>
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">From</span>
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">To</span>
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-1">
            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-cyan-500/20 text-cyan-200 hover:bg-cyan-500/30 text-sm font-medium">
                <i class="fas fa-filter mr-1"></i> Apply filters
            </button>
            @if($hasAnyFilter)
                <a href="{{ route('user.access.audit.index') }}"
                   class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/60 text-sm">Clear</a>
            @endif
            <span class="flex-1"></span>
            <a href="{{ route($exportRoute, $filters) }}"
               class="px-4 py-2 rounded-xl bg-emerald-500/20 text-emerald-200 hover:bg-emerald-500/30 text-sm font-medium">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
        </div>
    </form>

    <div class="rounded-2xl border border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-white/40">
                    <tr>
                        <th class="px-4 py-3 text-left">When</th>
                        <th class="px-4 py-3 text-left">Actor</th>
                        <th class="px-4 py-3 text-left">Action</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Target user</th>
                        <th class="px-4 py-3 text-left">Source</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($audits as $a)
                        <tr>
                            <td class="px-4 py-2 text-xs text-white/60 whitespace-nowrap"
                                title="{{ $a->created_at?->toDateTimeString() }}">
                                {{ $a->created_at?->toDateTimeString() }}
                                <div class="text-[10px] text-white/30">{{ $a->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-2 text-white">
                                {{ $a->actorLabel() }}
                                @if($a->actor_email)
                                    <div class="text-[11px] text-white/40">{{ $a->actor_email }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded-md text-xs
                                    {{ $a->action === 'attached' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300' }}">
                                    {{ $actions[$a->action] ?? $a->action }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <div class="text-white">{{ $a->role_name ?: $a->role_slug }}</div>
                                @if($a->role_name && $a->role_slug && $a->role_name !== $a->role_slug)
                                    <div class="text-[11px] text-white/40">{{ $a->role_slug }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <div class="text-white">
                                    {{ optional($a->targetUser)->name ?: ('User #' . $a->target_user_id) }}
                                </div>
                                @if(optional($a->targetUser)->email)
                                    <div class="text-[11px] text-white/40">{{ $a->targetUser->email }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-white/60">
                                {{ $sources[$a->source] ?? ($a->source ?: '—') }}
                            </td>
                            <td class="px-4 py-2 text-xs text-white/50 font-mono">{{ $a->ip ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-white/40">
                                @if($hasAnyFilter)
                                    No role changes match these filters.
                                @else
                                    No role changes recorded yet.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $audits->links() }}
    </div>
</div>
@endsection
