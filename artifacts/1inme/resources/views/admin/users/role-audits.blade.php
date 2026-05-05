@extends('admin.layouts.app')
@section('title', 'Role change audit log')
@section('page-title', 'Role change audit log')

@section('content')
@php
    $hasAnyFilter = collect($filters)->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
@endphp
<div class="space-y-6">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-white">Role change audit log</h2>
            <p class="text-sm text-white/50 max-w-3xl">
                Every grant and revoke against a user-pool role, from
                both the self-service "User access" page and this
                back-office area. Filters narrow the on-screen list and
                the CSV export uses the same filtered query.
            </p>
            @if($targetUser)
                <p class="text-xs text-white/40 mt-1">
                    Filtered to user
                    <a href="{{ route('admin.users.show', $targetUser) }}"
                       class="text-violet-300 hover:text-violet-200">
                        {{ $targetUser->name ?: $targetUser->email }} (#{{ $targetUser->id }})
                    </a>
                </p>
            @endif
        </div>
        <div class="flex gap-2 flex-wrap">
            @if(auth('admin')->user()?->isSuperAdmin())
                <a href="{{ route('admin.users.role-audit-exports.index') }}"
                   class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap"
                   title="Recent CSV downloads of this audit log">
                    <i class="fas fa-file-csv mr-1"></i> Download history
                </a>
            @endif
            <a href="{{ route('admin.users.index') }}"
               class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                <i class="fas fa-arrow-left mr-1"></i> Back to users
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.role-audits.index') }}"
          class="glass rounded-2xl border border-white/10 p-4 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Actor</span>
                <input type="text" name="actor" value="{{ $filters['actor'] }}"
                       placeholder="Name, email, or id"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500/40">
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Target user</span>
                <input type="text" name="target" value="{{ $filters['target'] }}"
                       placeholder="Name, email, or id"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500/40">
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Role</span>
                <select name="role"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                    <option value="">Any role</option>
                    @foreach($roleSlugs as $slug)
                        <option value="{{ $slug }}" @selected($filters['role'] === $slug)>{{ $slug }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Action</span>
                <select name="action"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                    <option value="">Any action</option>
                    @foreach($actions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['action'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">Source</span>
                <select name="source"
                        class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                    <option value="">Any source</option>
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}" @selected($filters['source'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">From</span>
                <input type="date" name="from" value="{{ $filters['from'] }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
            </label>
            <label class="block">
                <span class="block text-xs uppercase tracking-wide text-white/40 mb-1">To</span>
                <input type="date" name="to" value="{{ $filters['to'] }}"
                       class="w-full px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-1">
            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium">
                <i class="fas fa-filter mr-1"></i> Apply filters
            </button>
            @if($hasAnyFilter)
                <a href="{{ route('admin.users.role-audits.index') }}"
                   class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/60 text-sm">Clear</a>
            @endif
            <span class="flex-1"></span>
            <a href="{{ route('admin.users.role-audits.export', $filters) }}"
               class="px-4 py-2 rounded-xl bg-emerald-500/20 text-emerald-200 hover:bg-emerald-500/30 text-sm font-medium">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
        </div>
    </form>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
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
                                @if($a->targetUser)
                                    <a href="{{ route('admin.users.show', $a->targetUser) }}"
                                       class="text-violet-300 hover:text-violet-200">
                                        {{ $a->targetUser->name ?: $a->targetUser->email }}
                                    </a>
                                    @if($a->targetUser->email)
                                        <div class="text-[11px] text-white/40">{{ $a->targetUser->email }}</div>
                                    @endif
                                @else
                                    <span class="text-white/60">User #{{ $a->target_user_id }}</span>
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
