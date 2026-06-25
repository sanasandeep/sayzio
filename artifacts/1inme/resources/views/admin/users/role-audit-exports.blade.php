@extends('admin.layouts.app')
@section('title', 'Role audit downloads')
@section('page-title', 'Role audit downloads')

@section('content')
<div class="space-y-6">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-lg font-semibold text-white">Role audit downloads</h2>
            <p class="text-sm text-white/50 max-w-3xl">
                Every CSV download of the role-change audit is recorded
                here so you can spot unusual activity — very large
                pulls, repeated downloads from a single account, or
                exports of users that nobody on staff should be
                looking at. The CSVs themselves are produced from the
                self-service "User access" page and the back-office
                per-user role panel.
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.users.role-audits.index') }}"
               class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                <i class="fas fa-list mr-1"></i> Audit log
            </a>
            <a href="{{ route('admin.users.index') }}"
               class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                <i class="fas fa-arrow-left mr-1"></i> Back to users
            </a>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-white/5 text-xs uppercase tracking-wide text-white/40">
                    <tr>
                        <th class="px-4 py-3 text-left">When</th>
                        <th class="px-4 py-3 text-left">Operator</th>
                        <th class="px-4 py-3 text-left">Scope</th>
                        <th class="px-4 py-3 text-left">Target</th>
                        <th class="px-4 py-3 text-right">Rows</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @forelse($exports as $e)
                        <tr>
                            <td class="px-4 py-2 text-xs text-white/60 whitespace-nowrap"
                                title="{{ $e->created_at?->toDateTimeString() }}">
                                {{ $e->created_at?->toDateTimeString() }}
                                <div class="text-[10px] text-white/30">{{ $e->created_at?->diffForHumans() }}</div>
                            </td>
                            <td class="px-4 py-2 text-white">
                                {{ $e->actorLabel() }}
                                @if($e->actor_email)
                                    <div class="text-[11px] text-white/40">{{ $e->actor_email }}</div>
                                @endif
                                @if($e->actor_guard)
                                    <div class="text-[10px] uppercase tracking-wide text-white/30">{{ $e->actor_guard }} guard</div>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="px-2 py-0.5 rounded-md text-xs
                                    {{ $e->scope === 'full_pool' ? 'bg-amber-500/10 text-amber-300' : 'bg-sky-500/10 text-sky-300' }}">
                                    {{ $scopes[$e->scope] ?? $e->scopeLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                @if($e->scope === 'full_pool')
                                    <span class="text-white/40 text-xs">— (entire pool)</span>
                                @elseif($e->targetUser)
                                    <a href="{{ route('admin.users.show', $e->targetUser) }}"
                                       class="text-blue-300 hover:text-blue-200">
                                        {{ $e->targetUser->name ?: $e->targetUser->email }}
                                    </a>
                                    @if($e->targetUser->email)
                                        <div class="text-[11px] text-white/40">{{ $e->targetUser->email }}</div>
                                    @endif
                                @elseif($e->target_user_id)
                                    <span class="text-white/60">User #{{ $e->target_user_id }}</span>
                                @else
                                    <span class="text-white/40">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right text-white font-mono text-xs">
                                {{ number_format($e->row_count) }}
                            </td>
                            <td class="px-4 py-2 text-xs text-white/50 font-mono">{{ $e->ip ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-white/40">
                                No role-audit downloads recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $exports->links() }}
    </div>
</div>
@endsection
