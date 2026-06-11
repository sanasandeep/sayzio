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

    @php
        $hasAuditFilter = collect($auditFilters ?? [])->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
        $exportParams   = array_merge(['user' => $user->id], $auditFilters ?? []);
    @endphp

    <div class="glass rounded-2xl border border-white/10 p-6 mt-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white mb-1">Role change history</h3>
                <p class="text-xs text-white/50">
                    Every grant or revoke for {{ $user->name }}, newest first.
                </p>
            </div>
            <a href="{{ route('admin.users.roles.audit.export', $exportParams) }}"
               class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70 whitespace-nowrap"
               title="Download the role-change history for {{ $user->name }} matching the filters below as CSV.">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
        </div>

        {{-- Filter controls. Drive both the rendered list and the
             "Export CSV" link above. All filter state lives in the URL
             (GET) so a filtered view of this user's history is
             shareable. Source, range preset and from/to use the
             `audit_*` query params; actor/role/action use their own. --}}
        <form method="GET" action="{{ route('admin.users.roles.edit', $user) }}"
              class="rounded-xl border border-white/5 bg-white/[0.02] p-3 mb-4 space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Actor</span>
                    <input type="text" name="actor" value="{{ $auditFilters['actor'] ?? '' }}"
                           placeholder="Name, email, or id"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Role</span>
                    <select name="role"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                        <option value="">Any role</option>
                        @foreach(($auditRoleSlugs ?? []) as $slug)
                            <option value="{{ $slug }}" @selected(($auditFilters['role'] ?? '') === $slug)>{{ $slug }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Action</span>
                    <select name="action"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                        <option value="">Any action</option>
                        @foreach(($auditActions ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($auditFilters['action'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Source</span>
                    <select name="audit_source"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                        <option value="">Any source</option>
                        @foreach(($auditSources ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($auditFilters['audit_source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Range</span>
                    <select name="audit_range" data-testid="audit-range-filter"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                        <option value="">All time</option>
                        @foreach(($auditRanges ?? []) as $value => $label)
                            @continue($value === \App\Modules\User\Models\UserRoleAudit::RANGE_ALL)
                            <option value="{{ $value }}" @selected(($auditFilters['audit_range'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">From</span>
                    <input type="date" name="audit_from" value="{{ $auditFilters['audit_from'] ?? '' }}"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">To</span>
                    <input type="date" name="audit_to" value="{{ $auditFilters['audit_to'] ?? '' }}"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-violet-500/40">
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit"
                        class="px-3 py-1.5 rounded-lg bg-violet-500/20 text-violet-200 hover:bg-violet-500/30 text-xs font-medium">
                    <i class="fas fa-filter mr-1"></i> Apply filters
                </button>
                @if($hasAuditFilter)
                    <a href="{{ route('admin.users.roles.edit', $user) }}"
                       class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/60 text-xs">Clear filters</a>
                @endif
            </div>
        </form>

        @if(empty($audits) || $audits->isEmpty())
            <p class="text-sm text-white/40">
                @if($hasAuditFilter)
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
