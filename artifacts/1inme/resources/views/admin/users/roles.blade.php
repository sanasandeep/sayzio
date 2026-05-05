@extends('admin.layouts.app')
@section('title', 'User Roles')
@section('page-title', 'Roles for ' . $user->name)

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-white">{{ $user->name }}</h2>
                <p class="text-sm text-white/50">{{ $user->email }}</p>
            </div>
            <a href="{{ route('admin.users.show', $user) }}"
               class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70">
                <i class="fas fa-arrow-left mr-1"></i> Back to user
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <p class="text-xs text-white/50 mb-4">
            Roles below are scoped to the user pool (web guard). Each role
            grants a bundle of permissions used across the user-facing app.
        </p>

        <form method="POST" action="{{ route('admin.users.roles.update', $user) }}" class="space-y-3">
            @csrf @method('PUT')

            @forelse($roles as $role)
                <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 hover:bg-white/5 cursor-pointer">
                    <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"
                           class="mt-1"
                           {{ in_array($role->id, $assigned, true) ? 'checked' : '' }}>
                    <div>
                        <div class="text-sm font-medium text-white">{{ $role->name }}</div>
                        <div class="text-xs text-white/40">{{ $role->slug }}</div>
                        @if($role->description)
                            <div class="text-xs text-white/60 mt-1">{{ $role->description }}</div>
                        @endif
                    </div>
                </label>
            @empty
                <p class="text-sm text-white/50">No user-pool roles are defined.</p>
            @endforelse

            <div class="pt-3 flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl bg-violet-500/20 text-violet-200 hover:bg-violet-500/30 text-sm font-medium">
                    Save roles
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6 mt-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white mb-1">Role change history</h3>
                <p class="text-xs text-white/50">
                    Every grant or revoke for {{ $user->name }}, newest first.
                </p>
            </div>
            <a href="{{ route('admin.users.roles.audit.export', $user) }}"
               class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70 whitespace-nowrap"
               title="Download the full role-change history for {{ $user->name }} as CSV — not just the rows shown here.">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
        </div>

        {{-- Source chip filter. Each chip is a plain link that
             sets/clears `?audit_source=` so the URL is shareable and
             survives reloads. --}}
        <div class="flex flex-wrap items-center gap-2 mb-3" data-testid="audit-source-filter">
            <span class="text-xs text-white/40 mr-1">Filter:</span>
            <a href="{{ route('admin.users.roles.edit', $user) }}"
               data-source="all"
               class="px-2.5 py-1 rounded-full text-xs border
                   {{ empty($auditSource ?? null)
                       ? 'bg-white/15 text-white border-white/20'
                       : 'bg-white/[0.02] text-white/60 border-white/10 hover:bg-white/10' }}">
                All
            </a>
            @foreach(($auditFilters ?? []) as $filterValue => $filterLabel)
                <a href="{{ route('admin.users.roles.edit', ['user' => $user, 'audit_source' => $filterValue]) }}"
                   data-source="{{ $filterValue }}"
                   class="px-2.5 py-1 rounded-full text-xs border
                       {{ ($auditSource ?? null) === $filterValue
                           ? 'bg-white/15 text-white border-white/20'
                           : 'bg-white/[0.02] text-white/60 border-white/10 hover:bg-white/10' }}">
                    {{ $filterLabel }}
                </a>
            @endforeach
        </div>

        @if(empty($audits) || $audits->isEmpty())
            <p class="text-sm text-white/40">
                @if(!empty($auditSource ?? null))
                    No entries match this filter.
                @else
                    No role changes recorded yet.
                @endif
            </p>
        @else
            <ul class="divide-y divide-white/5 text-sm">
                @foreach($audits as $a)
                    <li class="py-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span class="text-white/40 text-xs whitespace-nowrap"
                              title="{{ $a->created_at?->toDateTimeString() }}">
                            {{ $a->created_at?->diffForHumans() }}
                        </span>
                        <span class="text-white">{{ $a->actorLabel() }}</span>
                        @if($a->source === 'backfill')
                            <span class="px-2 py-0.5 rounded-md text-xs bg-amber-500/10 text-amber-300 border border-amber-500/20"
                                  title="This entry was generated by a one-time backfill from the original role assignment's created_at timestamp. It does not represent a live action by a person.">
                                Backfilled
                            </span>
                        @endif
                        <span class="text-white/50">
                            @if($a->action === 'attached')
                                granted
                            @else
                                revoked
                            @endif
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-xs
                            {{ $a->action === 'attached' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-rose-500/10 text-rose-300' }}">
                            {{ $a->role_name ?: $a->role_slug }}
                        </span>
                        @if($a->source === 'user_access')
                            <span class="text-xs text-white/30">via user access page</span>
                        @endif
                        @if($a->ip)
                            <span class="text-xs text-white/30">· {{ $a->ip }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
