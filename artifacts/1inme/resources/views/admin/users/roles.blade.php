@extends('admin.layouts.app')
@section('title', 'User Roles')
@section('page-title', 'Roles for ' . $user->name)

@section('content')
<div class="max-w-2xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-white ak-strong">{{ $user->name }}</h2>
                <p class="text-sm text-white/50 ak-muted">{{ $user->email }}</p>
            </div>
            <a href="{{ route('admin.users.show', $user) }}"
               class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70 ak-strong">
                <i class="fas fa-arrow-left mr-1"></i> Back to user
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 text-emerald-300 text-sm ak-green">
                {{ session('success') }}
            </div>
        @endif

        <p class="text-xs text-white/50 mb-4 ak-muted">
            Roles below are scoped to the user pool (web guard). Each role
            grants a bundle of permissions used across the user-facing app.
        </p>

        @if($canAssignRoles)
        <form method="POST" action="{{ route('admin.users.roles.update', $user) }}" class="space-y-3">
            @csrf @method('PUT')

            @forelse($roles as $role)
                <label class="flex items-start gap-3 p-3 rounded-xl border border-white/10 hover:bg-white/5 cursor-pointer">
                    <input type="checkbox" name="role_ids[]" value="{{ $role->id }}"
                           class="mt-1"
                           {{ in_array($role->id, $assigned, true) ? 'checked' : '' }}>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-white ak-strong">{{ $role->name }}</div>
                        <div class="text-xs text-white/40 ak-note">{{ $role->slug }}</div>
                        @if($role->description)
                            <div class="text-xs text-white/60 mt-1 ak-muted">{{ $role->description }}</div>
                        @endif

                        {{-- Feature access this role unlocks (Part 1). --}}
                        @if($role->permissions->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($role->permissions as $perm)
                                    <span class="px-1.5 py-0.5 rounded-md text-[10px] bg-blue-500/10 text-blue-200 border border-blue-500/15 ak-blue"
                                          title="{{ $perm->slug }}">{{ $perm->name ?: $perm->slug }}</span>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-2 text-[10px] text-white/30 ak-note">No specific feature permissions, baseline access only.</div>
                        @endif
                    </div>
                </label>
            @empty
                <p class="text-sm text-white/50 ak-muted">No user-pool roles are defined.</p>
            @endforelse

            <div class="pt-3 flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 text-sm font-medium ak-blue">
                    Save roles
                </button>
            </div>
        </form>
        @else
        <p class="text-xs text-white/40 ak-note">You don't have permission to change this user's roles.</p>
        @endif
    </div>

    {{-- Admin access panel (Part 1: promote a user to admin / change the
         back-office role / revoke). Creating an admin record by matching
         email is what powers the seamless dashboard switch. --}}
    <div id="admin-access" class="glass rounded-2xl border border-blue-500/30 ring-1 ring-blue-500/20 p-6 mt-6 scroll-mt-24">
        @if(session('error'))
            <div class="mb-4 p-3 rounded-xl bg-rose-500/10 text-rose-300 text-sm ak-red">{{ session('error') }}</div>
        @endif

        <div class="flex items-start justify-between gap-3 mb-1">
            <h3 class="text-lg font-semibold text-white flex items-center gap-2 ak-strong">
                <i class="fas fa-user-shield text-blue-300 ak-blue"></i> Back-office admin access
            </h3>
            @if($adminAccount)
                <span class="px-2 py-0.5 rounded-md text-[10px] font-medium {{ $adminAccount->status === 'active' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 ak-green' : 'bg-amber-500/10 text-amber-300 border border-amber-500/20 ak-amber' }}">
                    {{ $adminAccount->status === 'active' ? 'Admin · active' : 'Admin · ' . ucfirst($adminAccount->status) }}
                </span>
            @else
                <span class="px-2 py-0.5 rounded-md text-[10px] text-white/40 border border-white/10 ak-note">Not an admin</span>
            @endif
        </div>
        <p class="text-xs text-white/50 mb-4 ak-muted">
            Admins are a separate pool linked to this user by email. Promoting
            grants back-office access and enables seamless dashboard switching.
        </p>

        @if($adminAccount)
            <div class="mb-4 text-xs text-white/60 ak-muted">
                Current role:
                <span class="text-white font-medium ak-strong">{{ $adminAccount->role->name ?? '—' }}</span>
                @if($adminAccount->role && $adminAccount->role->slug === 'super-admin')
                    <span class="ml-1 px-1.5 py-0.5 rounded bg-blue-500/15 text-blue-200 text-[10px] ak-blue">full access</span>
                @endif
            </div>
        @endif

        @if($canGrantAdmin)
            @if($adminRoles->isEmpty())
                <p class="text-xs text-white/40 ak-note">No admin-guard roles are defined yet.</p>
            @else
                <form method="POST" action="{{ route('admin.users.admin-access.grant', $user) }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Admin role</span>
                        <select name="role_id"
                                class="px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                            @foreach($adminRoles as $r)
                                <option value="{{ $r->id }}"
                                    @selected($adminAccount && (int) $adminAccount->role_id === (int) $r->id)>
                                    {{ $r->name }} ({{ $r->permissions->count() }} permissions)
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 text-xs font-medium ak-blue">
                        <i class="fas fa-user-shield mr-1"></i>{{ $adminAccount ? 'Update admin role' : 'Promote to admin' }}
                    </button>
                </form>

                {{-- Show what each admin role unlocks. --}}
                <div class="mt-4 space-y-2">
                    @foreach($adminRoles as $r)
                        <div class="rounded-xl border border-white/5 bg-white/[0.02] p-3">
                            <div class="text-xs font-medium text-white ak-strong">{{ $r->name }} <span class="text-white/30 ak-note">· {{ $r->slug }}</span></div>
                            @if($r->slug === 'super-admin')
                                <div class="mt-1 text-[10px] text-blue-200 ak-blue">Unrestricted, every permission.</div>
                            @elseif($r->permissions->isNotEmpty())
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach($r->permissions as $perm)
                                        <span class="px-1.5 py-0.5 rounded-md text-[10px] bg-white/5 text-white/60 border border-white/10 ak-muted"
                                              title="{{ $perm->slug }}">{{ $perm->name ?: $perm->slug }}</span>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-1 text-[10px] text-white/30 ak-note">No permissions assigned.</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <p class="text-xs text-white/40 ak-note">You don't have permission to change admin access.</p>
        @endif

        @if($adminAccount && $canRevokeAdmin)
            <form method="POST" action="{{ route('admin.users.admin-access.revoke', $user) }}" class="mt-4 pt-4 border-t border-white/5"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Revoke admin access?', message: 'This deletes the back-office admin record. The user account is untouched.', confirmText: 'Revoke', confirmIcon: 'fa-user-slash', iconClass: 'fa-user-slash'})">
                @csrf @method('DELETE')
                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 text-rose-300 hover:bg-rose-500/20 text-xs font-medium ak-red">
                    <i class="fas fa-user-slash mr-1"></i> Revoke admin access
                </button>
            </form>
        @endif
    </div>

    @php
        $hasAuditFilter = collect($auditFilters ?? [])->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
        $exportParams   = array_merge(['user' => $user->id], $auditFilters ?? []);
    @endphp

    <div class="glass rounded-2xl border border-white/10 p-6 mt-6">
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-white mb-1 ak-strong">Role change history</h3>
                <p class="text-xs text-white/50 ak-muted">
                    Every grant or revoke for {{ $user->name }}, newest first.
                </p>
            </div>
            <a href="{{ route('admin.users.roles.audit.export', $exportParams) }}"
               class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70 whitespace-nowrap ak-strong"
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
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Actor</span>
                    <input type="text" name="actor" value="{{ $auditFilters['actor'] ?? '' }}"
                           placeholder="Name, email, or id"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Role</span>
                    <select name="role"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                        <option value="">Any role</option>
                        @foreach(($auditRoleSlugs ?? []) as $slug)
                            <option value="{{ $slug }}" @selected(($auditFilters['role'] ?? '') === $slug)>{{ $slug }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Action</span>
                    <select name="action"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                        <option value="">Any action</option>
                        @foreach(($auditActions ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($auditFilters['action'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Source</span>
                    <select name="audit_source"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                        <option value="">Any source</option>
                        @foreach(($auditSources ?? []) as $value => $label)
                            <option value="{{ $value }}" @selected(($auditFilters['audit_source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">Range</span>
                    <select name="audit_range" data-testid="audit-range-filter"
                            class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                        <option value="">All time</option>
                        @foreach(($auditRanges ?? []) as $value => $label)
                            @continue($value === \App\Modules\User\Models\UserRoleAudit::RANGE_ALL)
                            <option value="{{ $value }}" @selected(($auditFilters['audit_range'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">From</span>
                    <input type="date" name="audit_from" value="{{ $auditFilters['audit_from'] ?? '' }}"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                </label>
                <label class="block">
                    <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1 ak-note">To</span>
                    <input type="date" name="audit_to" value="{{ $auditFilters['audit_to'] ?? '' }}"
                           class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40 ak-strong ak-input">
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit"
                        class="px-3 py-1.5 rounded-lg bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 text-xs font-medium ak-blue">
                    <i class="fas fa-filter mr-1"></i> Apply filters
                </button>
                @if($hasAuditFilter)
                    <a href="{{ route('admin.users.roles.edit', $user) }}"
                       class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/60 text-xs ak-muted">Clear filters</a>
                @endif
            </div>
        </form>

        @if(empty($audits) || $audits->isEmpty())
            <p class="text-sm text-white/40 ak-note">
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
                        <span class="text-white/40 text-xs whitespace-nowrap ak-note"
                              title="{{ $a->created_at?->toDateTimeString() }}">
                            {{ $a->created_at?->diffForHumans() }}
                        </span>
                        <span class="text-white ak-strong">{{ $a->actorLabel() }}</span>
                        @if($a->source === 'backfill')
                            <span class="px-2 py-0.5 rounded-md text-xs bg-amber-500/10 text-amber-300 border border-amber-500/20 ak-amber"
                                  title="This entry was generated by a one-time backfill from the original role assignment's created_at timestamp. It does not represent a live action by a person.">
                                Backfilled
                            </span>
                        @endif
                        <span class="text-white/50 ak-muted">
                            @if($a->action === 'attached')
                                granted
                            @else
                                revoked
                            @endif
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-xs
                            {{ $a->action === 'attached' ? 'bg-emerald-500/10 text-emerald-300 ak-green' : 'bg-rose-500/10 text-rose-300 ak-red' }}">
                            {{ $a->role_name ?: $a->role_slug }}
                        </span>
                        @if($a->source === 'user_access')
                            <span class="text-xs text-white/30 ak-note">via user access page</span>
                        @endif
                        @if($a->ip)
                            <span class="text-xs text-white/30 ak-note">· {{ $a->ip }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
