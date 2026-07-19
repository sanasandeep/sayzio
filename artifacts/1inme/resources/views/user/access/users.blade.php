@extends('user.layouts.app')
@section('title', 'User access')

@section('content')
<div class="max-w-4xl mx-auto p-4 lg:p-6 space-y-5">
    <header class="flex flex-col gap-1">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-white">User access</h1>
                <p class="text-sm text-white/50">
                    Promote or demote other users on the user pool. Roles listed
                    here are scoped to the user-facing app, back-office admin
                    roles are managed separately.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('user.access.audit.index') }}"
                   class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                    <i class="fas fa-clipboard-list mr-1"></i> Full audit log
                </a>
                <a href="{{ route('user.access.roles.index') }}"
                   class="px-3 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm font-medium whitespace-nowrap">
                    <i class="fas fa-sliders mr-1"></i> Edit roles
                </a>
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 text-emerald-300 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @php
        // Audit-panel filter state. Used to (a) auto-open the panel
        // when filters are active, (b) preserve them on the user
        // search form via hidden inputs, and (c) carry them into the
        // CSV export so the download mirrors the on-screen list.
        $hasAuditFilter = collect($auditFilters ?? [])->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
        $exportQuery = array_filter(array_merge($auditFilters ?? [], $search !== '' ? ['q' => $search] : []),
            fn ($v) => $v !== '' && $v !== null);
    @endphp

    <form method="GET" class="flex items-center gap-2">
        <input type="text" name="q" value="{{ $search }}"
               placeholder="Search by name or email…"
               class="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-sm text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
        {{-- Preserve the audit-panel filter state when the operator
             submits the user search so the panel doesn't snap back
             to "all changes" mid-review. --}}
        @foreach(($auditFilters ?? []) as $k => $v)
            @if($v !== '' && $v !== null)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach
        <button type="submit" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white/80 text-sm">
            Search
        </button>
        @if($search !== '')
            <a href="{{ route('user.access.users.index', $auditFilters ?? []) }}"
               class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/60 text-sm">Clear</a>
        @endif
    </form>

    @if($search === '')
        <p class="text-xs text-white/40">
            Showing users that already hold at least one role. Use search
            above to find a user that doesn't appear in this list yet.
        </p>
    @endif

    @php
        $hasAuditFilter = collect($auditFilters ?? [])->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
    @endphp

    @if($audits->count() > 0 || $hasAuditFilter)
        <details class="rounded-2xl border border-white/10 bg-white/[0.03]" {{ $hasAuditFilter ? 'open' : '' }}>
            <summary class="cursor-pointer px-4 py-3 text-sm text-white/80 select-none flex items-center justify-between gap-3">
                <span>
                    Recent role changes
                    <span class="ml-1 text-xs text-white/40">({{ $audits->count() }} {{ $hasAuditFilter ? 'matching' : 'latest' }})</span>
                </span>
                <a href="{{ route('user.access.users.audit.export', $auditFilters ?? []) }}"
                   onclick="event.stopPropagation();"
                   class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/15 text-white/80 text-xs font-medium whitespace-nowrap"
                   title="Download the role-change history matching the filters above as CSV.">
                    <i class="fas fa-file-csv mr-1"></i> Export CSV
                </a>
            </summary>
            <div class="px-4 pb-4 space-y-3">
                {{-- Filter controls: drive both the rendered list below
                     and the CSV export above. All filter state lives in
                     the URL (GET) so a filtered view is shareable.
                     Source, range preset and from/to use the `audit_*`
                     query params; actor/role/action use their own. --}}
                <form method="GET" action="{{ route('user.access.users.index') }}"
                      class="rounded-xl border border-white/5 bg-white/[0.02] p-3 space-y-3"
                      onclick="event.stopPropagation();">
                    @if($search !== '')
                        <input type="hidden" name="q" value="{{ $search }}">
                    @endif
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        <label class="block">
                            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Actor</span>
                            <input type="text" name="actor" value="{{ $auditFilters['actor'] ?? '' }}"
                                   placeholder="Name, email, or id"
                                   class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Role</span>
                            <select name="role"
                                    class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                                <option value="">Any role</option>
                                @foreach(($auditRoleSlugs ?? []) as $slug)
                                    <option value="{{ $slug }}" @selected(($auditFilters['role'] ?? '') === $slug)>{{ $slug }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Action</span>
                            <select name="action"
                                    class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                                <option value="">Any action</option>
                                @foreach(($auditActions ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($auditFilters['action'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Source</span>
                            <select name="audit_source"
                                    class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                                <option value="">Any source</option>
                                @foreach(($auditSources ?? []) as $value => $label)
                                    <option value="{{ $value }}" @selected(($auditFilters['audit_source'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Range</span>
                            <select name="audit_range" data-testid="audit-range-filter"
                                    class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
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
                                   class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        </label>
                        <label class="block">
                            <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">To</span>
                            <input type="date" name="audit_to" value="{{ $auditFilters['audit_to'] ?? '' }}"
                                   class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500/40">
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-cyan-500/20 text-cyan-200 hover:bg-cyan-500/30 text-xs font-medium">
                            <i class="fas fa-filter mr-1"></i> Apply filters
                        </button>
                        @if($hasAuditFilter)
                            <a href="{{ route('user.access.users.index', $search !== '' ? ['q' => $search] : []) }}"
                               class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/60 text-xs">Clear filters</a>
                        @endif
                    </div>
                </form>

                @if($audits->isEmpty())
                    <p class="text-sm text-white/40 py-2">No entries match this filter.</p>
                @else
                    <ul class="divide-y divide-white/5 text-sm">
                        @foreach($audits as $a)
                            {{-- id anchor lets the platform-role alert email link
                                 straight to a single audit row via #audit-{id}. --}}
                            <li id="audit-{{ $a->id }}" class="py-2 flex flex-wrap items-baseline gap-x-2 gap-y-1 target:bg-amber-500/10 target:rounded-lg target:px-2">
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
                                <span class="text-white/50">on</span>
                                <span class="text-white">
                                    {{ optional($a->targetUser)->name ?: ('User #' . $a->target_user_id) }}
                                </span>
                                @if($a->source === 'admin')
                                    <span class="text-xs text-white/30">via back-office</span>
                                @endif
                                @if($a->ip)
                                    <span class="text-xs text-white/30">· {{ $a->ip }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </details>
    @endif

    <div class="space-y-3">
        @forelse($users as $u)
            <form method="POST"
                  action="{{ route('user.access.users.update', ['user' => $u->id, 'q' => $search]) }}"
                  class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                @csrf
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-medium text-white">{{ $u->name }}</div>
                        <div class="text-xs text-white/50">{{ $u->email }}</div>
                    </div>
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg bg-cyan-500/20 text-cyan-200 hover:bg-cyan-500/30 text-xs font-medium">
                        Save
                    </button>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach($roles as $role)
                        @php $checked = $u->roles->contains('id', $role->id); @endphp
                        <label class="flex items-start gap-2 text-sm text-white/80 p-2 rounded-lg hover:bg-white/5 cursor-pointer">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" {{ $checked ? 'checked' : '' }}>
                            <span>
                                <span class="text-white">{{ $role->name }}</span>
                                <span class="block text-xs text-white/40">{{ $role->slug }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </form>
        @empty
            <div class="text-sm text-white/50 p-4 rounded-xl border border-white/10 bg-white/[0.02]">
                No matching users.
            </div>
        @endforelse
    </div>
</div>
@endsection
