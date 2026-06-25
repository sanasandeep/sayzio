@extends('admin.layouts.app')
@section('title', 'User Details')
@section('page-title', 'User: ' . $user->name)

@section('content')
@if($user->isSuspended())
<div class="mb-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 flex flex-wrap items-center justify-between gap-3">
    <div class="text-sm text-rose-200">
        <i class="fas fa-ban mr-1"></i>
        <span class="font-semibold">Account suspended</span>
        @if($user->suspension_reason)<span class="text-rose-200/80">— {{ $user->suspension_reason }}</span>@endif
        @if($user->reactivate_at)
            <span class="block text-xs text-rose-200/60 mt-1">Auto-reactivates on {{ $user->reactivate_at->format('M j, Y') }}</span>
        @endif
    </div>
    @if(auth('admin')->user()?->hasPermission('users.edit'))
    <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
        @csrf
        <button type="submit" class="px-4 py-2 bg-emerald-500/20 text-emerald-200 rounded-xl text-sm font-medium hover:bg-emerald-500/30 transition">
            <i class="fas fa-unlock mr-1"></i> Reactivate now
        </button>
    </form>
    @endif
</div>
@endif
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
        <div class="glass rounded-2xl border border-white/10  p-6">
            <div class="text-center">
                <div class="w-20 h-20 rounded-full bg-blue-500/10 text-blue-300 flex items-center justify-center text-2xl font-bold mx-auto">
                    {{ substr($user->name, 0, 1) }}
                </div>
                <h2 class="mt-4 text-lg font-semibold text-white">{{ $user->name }}</h2>
                <p class="text-sm text-white/40">{{ $user->email }}</p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2
                    {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                    {{ ucfirst($user->status) }}
                </span>
            </div>

            <div class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-white/40">Phone</span><span class="text-white">{{ $user->phone ?? 'N/A' }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Plan</span><span class="text-white">{{ $user->plan->name ?? 'Free' }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Joined</span><span class="text-white">{{ $user->created_at->format('M d, Y') }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Last Login</span><span class="text-white">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</span></div>
                <div class="flex justify-between"><span class="text-white/40">Timezone</span><span class="text-white">{{ $user->timezone }}</span></div>
            </div>

            @if(auth('admin')->user()?->hasPermission('users.impersonate'))
            <div class="mt-6 flex gap-2">
                <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 bg-amber-500/10 text-amber-400 rounded-xl text-sm font-medium hover:bg-amber-500/10 transition">
                        <i class="fas fa-user-secret mr-1"></i> Login as User
                    </button>
                </form>
            </div>
            @endif
            <div class="mt-3">
                <a href="{{ route('admin.users.roles.edit', $user) }}"
                   class="block w-full text-center px-4 py-2 bg-blue-500/10 text-blue-300 rounded-xl text-sm font-medium hover:bg-blue-500/20 transition">
                    <i class="fas fa-user-shield mr-1"></i> Manage roles
                </a>
            </div>
        </div>
    </div>

    <div class="lg:col-span-2">
        <div class="glass rounded-2xl border border-white/10  p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Edit User</h3>
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf @method('PUT')
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Name</label>
                        <input type="text" name="name" value="{{ $user->name }}"
                               class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-blue-500/40 outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/80 mb-1">Status</label>
                            <select name="status" @if(!empty($isProtected)) disabled @endif class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-blue-500/40 outline-none @if(!empty($isProtected)) opacity-60 cursor-not-allowed @endif">
                                <option value="active" {{ $user->status == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $user->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="banned" {{ $user->status == 'banned' ? 'selected' : '' }}>Banned</option>
                            </select>
                            @if(!empty($isProtected))
                            {{-- A disabled select isn't submitted; keep the current status in the payload so this account can never be suspended/banned via the edit form. --}}
                            <input type="hidden" name="status" value="{{ $user->status }}">
                            <p class="mt-1 text-xs text-emerald-400/80"><i class="fas fa-shield-alt mr-1"></i> Protected account — status is locked.</p>
                            @endif
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/80 mb-1">Plan</label>
                            <select name="plan_id" class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-blue-500/40 outline-none">
                                <option value="">No Plan</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ $user->plan_id == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Plan Expires At</label>
                        <input type="datetime-local" name="plan_expires_at" value="{{ $user->plan_expires_at?->format('Y-m-d\TH:i') }}"
                               class="w-full px-4 py-2.5 border border-white/10 rounded-xl focus:ring-2 focus:ring-blue-500/40 outline-none">
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(auth('admin')->user()?->hasPermission('users.edit'))
    <div class="lg:col-span-3 grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Assign plan, optionally as a complimentary / time-limited grant.
             Leaving "Complimentary days" blank assigns the plan permanently
             and clears any prior comp window. --}}
        <div class="glass rounded-2xl border border-white/10 p-6">
            <h3 class="text-lg font-semibold text-white mb-1">Assign plan</h3>
            <p class="text-xs text-white/40 mb-4">Includes internal plans. Add a comp window to auto-revert to the default plan.</p>
            <form method="POST" action="{{ route('admin.users.assign-plan', $user) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Plan</label>
                    <select name="plan_id" required
                            class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" {{ $user->plan_id == $plan->id ? 'selected' : '' }}>
                                {{ $plan->name }}{{ $plan->is_internal ? ' (internal)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/80 mb-1">Complimentary days <span class="text-white/40">(optional)</span></label>
                    <input type="number" name="comp_days" min="1" max="3650" placeholder="e.g. 30 — leave blank for permanent"
                           class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-blue-500/40 outline-none">
                    @if($user->comp_plan_expires_at)
                        <p class="text-xs text-amber-300/80 mt-1">Current comp window ends {{ $user->comp_plan_expires_at->format('M j, Y') }}.</p>
                    @endif
                </div>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">
                    Assign plan
                </button>
            </form>
        </div>

        {{-- Suspend / reactivate. Suspension takes a required reason and an
             optional auto-reactivation date enforced at login + by a job. --}}
        <div class="glass rounded-2xl border border-white/10 p-6">
            @if(!empty($isProtected))
                <h3 class="text-lg font-semibold text-white mb-1"><i class="fas fa-shield-alt text-emerald-400 mr-1"></i> Protected account</h3>
                <p class="text-xs text-white/40">This account is on the protected list and cannot be suspended or deleted. Manage protection from <a href="{{ route('admin.protected-accounts.index') }}" class="text-blue-300 hover:text-blue-200 underline">Protected accounts</a>.</p>
            @elseif($user->isSuspended())
                <h3 class="text-lg font-semibold text-white mb-1">Reactivate account</h3>
                <p class="text-xs text-white/40 mb-4">This account is currently on hold. Lift it immediately below.</p>
                <form method="POST" action="{{ route('admin.users.reactivate', $user) }}">
                    @csrf
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-medium hover:bg-emerald-700 transition">
                        <i class="fas fa-unlock mr-1"></i> Reactivate now
                    </button>
                </form>
            @else
                <h3 class="text-lg font-semibold text-white mb-1">Suspend account</h3>
                <p class="text-xs text-white/40 mb-4">Blocks sign-in with a reason. Optionally auto-reactivate on a date.</p>
                <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Reason</label>
                        <textarea name="reason" rows="2" required maxlength="1000" placeholder="Shown to the user at sign-in"
                                  class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:ring-2 focus:ring-blue-500/40 outline-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-1">Auto-reactivate at <span class="text-white/40">(optional)</span></label>
                        <input type="datetime-local" name="reactivate_at"
                               class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:ring-2 focus:ring-blue-500/40 outline-none">
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-rose-600 text-white rounded-xl font-medium hover:bg-rose-700 transition">
                        <i class="fas fa-ban mr-1"></i> Suspend account
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endif

    @php
        // The user-detail route is gated by `users.view`, but the task
        // restricts role-change audit visibility to operators with
        // `users.edit` (the same permission that lets them mutate
        // roles). Hide the panel for read-only viewers.
        $canSeeRoleAudits = optional(auth('admin')->user())->hasPermission('users.edit') ?? false;
    @endphp

    @if($canSeeRoleAudits)
    @php
        $hasAuditFilter = collect($auditFilters ?? [])->filter(fn ($v) => $v !== '' && $v !== null)->isNotEmpty();
        $exportParams   = array_merge(['user' => $user->id], $auditFilters ?? []);
    @endphp
    <div class="lg:col-span-3">
        <div class="glass rounded-2xl border border-white/10 p-6">
            <div class="flex items-center justify-between mb-4 gap-2 flex-wrap">
                <div>
                    <h3 class="text-white font-semibold">Role change history</h3>
                    <p class="text-xs text-white/40">Latest grants / revokes against this user.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.users.role-audits.index', ['target' => $user->id]) }}"
                       class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70">
                        <i class="fas fa-clipboard-list mr-1"></i> Full audit log
                    </a>
                    <a href="{{ route('admin.users.roles.audit.export', $exportParams) }}"
                       class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70 whitespace-nowrap"
                       title="Download the role-change history for {{ $user->name }} matching the filters below as CSV.">
                        <i class="fas fa-file-csv mr-1"></i> Export CSV
                    </a>
                    <a href="{{ route('admin.users.roles.edit', $user) }}"
                       class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/70">
                        Manage roles
                    </a>
                </div>
            </div>

            {{-- Filter controls. Drive both the rendered list and the
                 "Export CSV" link above. All filter state lives in the
                 URL (GET) so a filtered view of this user's history is
                 shareable. Source, range preset and from/to use the
                 `audit_*` query params; actor/role/action use their own. --}}
            <form method="GET" action="{{ route('admin.users.show', $user) }}"
                  class="rounded-xl border border-white/5 bg-white/[0.02] p-3 mb-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Actor</span>
                        <input type="text" name="actor" value="{{ $auditFilters['actor'] ?? '' }}"
                               placeholder="Name, email, or id"
                               class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white placeholder:text-white/30 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Role</span>
                        <select name="role"
                                class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                            <option value="">Any role</option>
                            @foreach(($auditRoleSlugs ?? []) as $slug)
                                <option value="{{ $slug }}" @selected(($auditFilters['role'] ?? '') === $slug)>{{ $slug }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Action</span>
                        <select name="action"
                                class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                            <option value="">Any action</option>
                            @foreach(($auditActions ?? []) as $value => $label)
                                <option value="{{ $value }}" @selected(($auditFilters['action'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Source</span>
                        <select name="audit_source"
                                class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                            <option value="">Any source</option>
                            @foreach(($auditSources ?? []) as $value => $label)
                                <option value="{{ $value }}" @selected(($auditFilters['audit_source'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Range</span>
                        <select name="audit_range" data-testid="audit-range-filter"
                                class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
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
                               class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">To</span>
                        <input type="date" name="audit_to" value="{{ $auditFilters['audit_to'] ?? '' }}"
                               class="w-full px-2.5 py-1.5 rounded-lg bg-white/5 border border-white/10 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit"
                            class="px-3 py-1.5 rounded-lg bg-blue-500/20 text-blue-200 hover:bg-blue-500/30 text-xs font-medium">
                        <i class="fas fa-filter mr-1"></i> Apply filters
                    </button>
                    @if($hasAuditFilter)
                        <a href="{{ route('admin.users.show', $user) }}"
                           class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-white/60 text-xs">Clear filters</a>
                    @endif
                </div>
            </form>

            @if(empty($roleAudits) || $roleAudits->isEmpty())
                <p class="text-sm text-white/40">
                    @if($hasAuditFilter)
                        No entries match this filter.
                    @else
                        No role changes recorded yet.
                    @endif
                </p>
            @else
                <ul class="divide-y divide-white/5 text-sm">
                    @foreach($roleAudits as $a)
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
    @endif

    @if($walletEnabled)
    <div class="glass rounded-2xl border border-white/10 p-6 mt-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-white font-semibold">Wallet</h3>
                <p class="text-xs text-white/40">Coin balance and recent transactions.</p>
            </div>
            <div class="text-2xl font-bold text-amber-300">{{ number_format($wallet->balance) }} 🪙</div>
        </div>

        <form method="POST" action="{{ route('admin.users.wallet.adjust', $user) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
            @csrf
            <input type="number" name="delta" placeholder="Δ coins (use - to debit)" required
                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            <input type="text" name="reason" placeholder="Reason (required)" required
                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm md:col-span-1">
            <button class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">Apply adjustment</button>
        </form>

        @if($walletTransactions->isEmpty())
            <p class="text-sm text-white/40">No transactions yet.</p>
        @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">When</th><th class="text-left">Type</th>
                <th class="text-right">Δ Coins</th><th class="text-right">Balance</th>
                <th class="text-left pl-3">Reason</th>
            </tr></thead>
            <tbody>
            @foreach($walletTransactions as $tx)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white/60">{{ $tx->created_at->diffForHumans() }}</td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                    <td class="text-right font-semibold {{ $tx->delta_coins >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                        {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                    </td>
                    <td class="text-right text-white/80">{{ number_format($tx->balance_after) }}</td>
                    <td class="pl-3 text-white/50">{{ $tx->reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif
</div>
@endsection
