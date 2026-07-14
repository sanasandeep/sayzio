@extends('admin.layouts.app')
@section('title', 'Users')
@section('page-title', 'User Management')

@php($operator = auth('admin')->user())
@php($canCreateUsers = $operator?->hasPermission('users.create'))
@php($canBulkPlan = $operator?->hasPermission('users.bulk_plan'))
@php($canBulkCredits = $operator?->hasPermission('users.bulk_credits'))
@php($canBulkBadges = $operator?->hasPermission('users.edit'))
@php($canBulk = $canBulkPlan || $canBulkCredits)
@php($canDeleteUsers = $operator?->hasPermission('users.delete'))
@section('content')
<div @if($canBulk) x-data="bulkUsers()" @endif>
@if(request('promote'))
<div class="mb-6 flex items-start gap-3 p-4 rounded-2xl bg-blue-500/10 border border-blue-500/20">
    <i class="fas fa-user-shield text-blue-300 mt-0.5"></i>
    <div class="text-sm text-blue-100">
        <span class="font-medium">Promote an existing user to admin.</span>
        Find the person below, then click the
        <i class="fas fa-user-shield mx-0.5"></i> shield action on their row to grant back-office admin access.
    </div>
</div>
@endif
<div class="flex items-center justify-between mb-6 gap-3 flex-wrap">
    <form method="GET" class="flex items-center gap-2 flex-wrap">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users..."
               class="px-4 py-2 border border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/40 outline-none">
        <select name="status" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
            <option value="">All Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            <option value="banned" {{ request('status') == 'banned' ? 'selected' : '' }}>Banned</option>
        </select>
        <select name="plan" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
            <option value="">All Plans</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" {{ request('plan') == $plan->id ? 'selected' : '' }}>{{ $plan->name }}</option>
            @endforeach
        </select>
        @if($badges->isNotEmpty())
        <select name="badge" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
            <option value="">All Badges</option>
            @foreach($badges as $badge)
                <option value="{{ $badge->id }}" {{ request('badge') == $badge->id ? 'selected' : '' }}>{{ $badge->name }}</option>
            @endforeach
        </select>
        @endif
        <button type="submit" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm hover:bg-white/[0.06]">Filter</button>
    </form>
    @if($canCreateUsers)
    <a href="{{ route('admin.users.create') }}"
       class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition whitespace-nowrap">
        <i class="fas fa-user-plus mr-1"></i> Create account
    </a>
    @endif
</div>

@if($canBulk)
{{-- Bulk action bar — appears once one or more users are selected. Submits
     a single POST to the bulk endpoint; selected ids are injected as
     hidden inputs by Alpine. Grant-coins idempotency is handled server-side. --}}
<div x-show="selected.length" x-cloak
     class="mb-4 glass rounded-2xl border border-blue-500/20 p-4">
    <form method="POST" action="{{ route('admin.users.bulk') }}" class="flex flex-wrap items-end gap-3">
        @csrf
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="user_ids[]" :value="id">
        </template>
        <div class="text-sm text-white/70 mr-2">
            <span class="font-semibold text-blue-300" x-text="selected.length"></span> selected
        </div>
        <div>
            <label class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Action</label>
            <select name="action" x-model="action"
                    class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white">
                @if($canBulkPlan)<option value="assign_plan">Assign plan</option>@endif
                @if($canBulkCredits)<option value="grant_coins">Grant coins</option>@endif
                @if($canBulkBadges && $badges->isNotEmpty())
                <option value="assign_badge">Assign badge</option>
                <option value="remove_badge">Remove badge</option>
                @endif
            </select>
        </div>
        @if($canBulkBadges && $badges->isNotEmpty())
        <div x-show="action === 'assign_badge' || action === 'remove_badge'">
            <label class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Badge</label>
            <select name="badge_id" class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white">
                @foreach($badges as $badge)
                    <option value="{{ $badge->id }}">{{ $badge->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div x-show="action === 'assign_plan'">
            <label class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Plan</label>
            <select name="plan_id" class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white">
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}{{ $plan->is_internal ? ' (internal)' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div x-show="action === 'grant_coins'">
            <label class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Coins</label>
            <input type="number" name="coins" min="1" max="1000000" placeholder="100"
                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white w-28">
        </div>
        <div x-show="action === 'grant_coins'">
            <label class="block text-[10px] uppercase tracking-wide text-white/40 mb-1">Reason</label>
            <input type="text" name="reason" maxlength="255" placeholder="Reason"
                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white">
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">
            Apply to selected
        </button>
        <button type="button" @click="selected = []; document.querySelectorAll('.bulk-cb').forEach(c => c.checked = false)"
                class="px-3 py-2 text-white/50 hover:text-white/80 text-sm">Clear</button>
    </form>
</div>
@endif

<div class="glass rounded-2xl border border-white/10 overflow-hidden p-3">
    <table class="enhanced-table w-full">
        <thead class="bg-white/5">
            <tr>
                @if($canBulk)
                <th class="px-4 py-3 text-left" data-no-sort>
                    <input type="checkbox" class="rounded bg-white/5 border-white/20"
                           @change="toggleAll($event)" title="Select all on this page">
                </th>
                @endif
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">User</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Plan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Joined</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-white/40 uppercase" data-no-sort>Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($users as $user)
            <tr class="hover:bg-white/5">
                @if($canBulk)
                <td class="px-4 py-4">
                    <input type="checkbox" value="{{ $user->id }}" class="bulk-cb rounded bg-white/5 border-white/20"
                           @change="toggle({{ $user->id }}, $event)">
                </td>
                @endif
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-300 flex items-center justify-center text-sm font-medium">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">{{ $user->name }}</p>
                            <p class="text-xs text-white/30">{{ $user->email }}</p>
                            @php($userAdmin = $adminAccounts[strtolower(trim((string) $user->email))] ?? null)
                            @if($userAdmin)
                                <span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium {{ $userAdmin->status === 'active' ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-300 border border-amber-500/20' }}">
                                    <i class="fas fa-user-shield"></i>
                                    {{ $userAdmin->status === 'active' ? 'Admin · active' : 'Admin · ' . ucfirst($userAdmin->status) }}
                                </span>
                            @else
                                <span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] text-white/30 border border-white/10">Not an admin</span>
                            @endif
                            @if($user->accountBadges->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach($user->accountBadges as $badge)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium"
                                          style="background: {{ $badge->color }}1f; color: {{ $badge->color }};">
                                        <i class="fas fa-certificate text-[8px]"></i>{{ $badge->name }}
                                    </span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-white/60">{{ $user->plan->name ?? 'Free' }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $user->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : ($user->status === 'banned' ? 'bg-red-500/10 text-red-400' : 'bg-yellow-500/10 text-yellow-400') }}">
                        {{ ucfirst($user->status) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-white/40">{{ $user->created_at->format('M d, Y') }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.users.show', $user) }}" class="text-white/30 hover:text-blue-400" title="View"><i class="fas fa-eye"></i></a>
                        @if($canManageAdminAccess)
                        <a href="{{ route('admin.users.roles.edit', $user) }}#admin-access"
                           class="{{ $userAdmin ? 'text-blue-300 hover:text-blue-200' : 'text-white/30 hover:text-blue-400' }}"
                           title="Manage admin access"><i class="fas fa-user-shield"></i></a>
                        @endif
                        @if(auth('admin')->user()?->hasPermission('users.impersonate'))
                        <form action="{{ route('admin.users.impersonate', $user) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-white/30 hover:text-amber-400" title="Login as user"><i class="fas fa-user-secret"></i></button>
                        </form>
                        @endif
                        @if($canDeleteUsers)
                        @if((isset($protectedEmails) && $protectedEmails->has(strtolower(trim((string) $user->email)))) || (isset($protectedUserIds) && $protectedUserIds->has($user->id)))
                        <span class="text-emerald-400/70" title="Protected — cannot be deleted or suspended"><i class="fas fa-shield-alt"></i></span>
                        @else
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this user?', message: 'This cannot be undone.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-white/30 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="{{ $canBulk ? 6 : 5 }}" class="px-6 py-8 text-center text-white/30">No users found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-white/10">{{ $users->links() }}</div>
    @endif
</div>
</div>
@include('common.partials.enhanced-table')
@if($canBulk)
<script>
    function bulkUsers() {
        return {
            selected: [],
            action: '{{ $canBulkPlan ? 'assign_plan' : 'grant_coins' }}',
            toggle(id, e) {
                if (e.target.checked) {
                    if (!this.selected.includes(id)) this.selected.push(id);
                } else {
                    this.selected = this.selected.filter(x => x !== id);
                }
            },
            toggleAll(e) {
                const checked = e.target.checked;
                document.querySelectorAll('.bulk-cb').forEach(c => {
                    c.checked = checked;
                    const id = parseInt(c.value, 10);
                    if (checked) {
                        if (!this.selected.includes(id)) this.selected.push(id);
                    } else {
                        this.selected = this.selected.filter(x => x !== id);
                    }
                });
            },
        };
    }
</script>
@endif
@endsection
