@extends('admin.layouts.app')
@section('title', 'Staff Management')
@section('page-title', 'Staff Management')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-4">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search staff..."
                   class="px-4 py-2 border border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-violet-500/40 outline-none">
            <select name="status" class="px-3 py-2 border border-white/10 rounded-xl text-sm">
                <option value="">All Status</option>
                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm hover:bg-white/[0.06]">Filter</button>
        </form>
    </div>
    <div class="flex items-center gap-2">
        @if(auth('admin')->user()?->hasPermission('staff.create'))
        <button type="button" @click="$dispatch('open-promote-user')" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm font-medium hover:bg-white/[0.15] transition" title="Grant back-office admin access to a user who already has an account">
            <i class="fas fa-user-shield mr-2"></i>Promote existing user
        </button>
        @endif
        @if(auth('admin')->user()?->hasPermission('staff.create'))
        <button type="button" @click="$dispatch('open-add-staff')" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition" title="Create a brand-new back-office staff member">
            <i class="fas fa-plus mr-2"></i>Add Staff
        </button>
        @else
        <a href="{{ route('admin.staff.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Staff
        </a>
        @endif
    </div>
</div>

<div class="glass rounded-2xl border border-white/10 overflow-hidden p-3">
    <table class="enhanced-table w-full">
        <thead class="bg-white/5">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Email</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Role</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-white/40 uppercase">Last Login</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-white/40 uppercase" data-no-sort>Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
            @forelse($staff as $member)
            <tr class="hover:bg-white/5">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-violet-500/10 text-violet-300 flex items-center justify-center text-sm font-medium">
                            {{ substr($member->name, 0, 1) }}
                        </div>
                        <span class="text-sm font-medium text-white">{{ $member->name }}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-sm text-white/40">{{ $member->email }}</td>
                <td class="px-6 py-4 text-sm text-white/60">{{ $member->role->name ?? 'N/A' }}</td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $member->status === 'active' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
                        {{ ucfirst($member->status) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-white/40">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('admin.staff.edit', $member) }}" class="text-white/30 hover:text-violet-400"><i class="fas fa-edit"></i></a>
                        @if(isset($protectedEmails) && $protectedEmails->has(strtolower(trim((string) $member->email))))
                        <span class="text-emerald-400/70" title="Protected — cannot be deleted or deactivated"><i class="fas fa-shield-alt"></i></span>
                        @elseif($member->id !== auth()->guard('admin')->id())
                        <form action="{{ route('admin.staff.destroy', $member) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove this staff member?', message: 'They will lose admin access immediately.', confirmText: 'Remove', confirmIcon: 'fa-user-minus', iconClass: 'fa-user-minus'})">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-white/30 hover:text-red-400"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-6 py-8 text-center text-white/30">No staff members found</td></tr>
            @endforelse
        </tbody>
    </table>
    @if($staff->hasPages())
    <div class="px-6 py-4 border-t border-white/10">{{ $staff->links() }}</div>
    @endif
</div>
@include('common.partials.enhanced-table')

@if(auth('admin')->user()?->hasPermission('staff.create'))
<div x-data="promoteUser()"
     x-show="open"
     x-cloak
     @open-promote-user.window="show()"
     @keydown.escape.window="close()"
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     style="display:none">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close()"></div>
    <div class="relative glass border border-white/10 rounded-2xl w-full max-w-lg p-6 shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-white"><i class="fas fa-user-shield text-violet-400 mr-2"></i>Promote existing user</h3>
                <p class="text-sm text-white/40 mt-1">Search a user account and grant back-office admin access in place.</p>
            </div>
            <button type="button" @click="close()" class="text-white/30 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <div class="relative">
            <label class="block text-xs font-medium text-white/40 uppercase mb-1">User</label>
            <template x-if="!selected">
                <div>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-white/30 text-sm"></i>
                        <input type="text" x-model="query" @input.debounce.300ms="search()" x-ref="searchInput"
                               placeholder="Search by name or email…" autocomplete="off"
                               class="w-full pl-9 pr-4 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    </div>
                    <div class="mt-2 max-h-56 overflow-y-auto rounded-xl border border-white/10 divide-y divide-white/5" x-show="query.length >= 2" x-cloak>
                        <template x-if="loading">
                            <div class="px-4 py-3 text-sm text-white/40"><i class="fas fa-spinner fa-spin mr-2"></i>Searching…</div>
                        </template>
                        <template x-if="!loading && results.length === 0">
                            <div class="px-4 py-3 text-sm text-white/40">No matching users found.</div>
                        </template>
                        <template x-for="user in results" :key="user.id">
                            <button type="button" @click="pick(user)" class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-white/5">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-white truncate" x-text="user.name"></div>
                                    <div class="text-xs text-white/40 truncate" x-text="user.email"></div>
                                </div>
                                <span x-show="user.is_admin" class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-500/10 text-amber-400">Already admin</span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            <template x-if="selected">
                <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white/5 border border-white/10 rounded-xl">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-white truncate" x-text="selected.name"></div>
                        <div class="text-xs text-white/40 truncate" x-text="selected.email"></div>
                        <div x-show="selected.is_admin" class="text-[11px] text-amber-400 mt-0.5">This user already has admin access — granting will update their role.</div>
                    </div>
                    <button type="button" @click="clear()" class="shrink-0 text-white/30 hover:text-white text-sm"><i class="fas fa-times mr-1"></i>Change</button>
                </div>
            </template>
        </div>

        <form method="POST" :action="grantUrl" class="mt-4" x-show="selected" x-cloak @submit="if (!handleGrantSubmit($event)) $event.preventDefault()">
            @csrf
            <input type="hidden" name="redirect_to" value="staff">
            <label class="block text-xs font-medium text-white/40 uppercase mb-1">Admin role</label>
            <select name="role_id" x-model="roleId" x-ref="roleSelect" required
                    class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                <option value="">Select a role…</option>
                @foreach($adminRoles as $role)
                <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
            </select>
            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" @click="close()" class="px-4 py-2 bg-white/5 text-white/70 rounded-xl text-sm hover:bg-white/10">Cancel</button>
                <button type="submit" :disabled="!roleId" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <i class="fas fa-user-shield mr-2"></i>Grant admin access
                </button>
            </div>
        </form>
    </div>
</div>

<div x-data="addStaff()"
     x-show="open"
     x-cloak
     @open-add-staff.window="show()"
     @keydown.escape.window="close()"
     class="fixed inset-0 z-[100] flex items-center justify-center p-4"
     style="display:none">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close()"></div>
    <div class="relative glass border border-white/10 rounded-2xl w-full max-w-lg p-6 shadow-2xl"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-white"><i class="fas fa-user-plus text-violet-400 mr-2"></i>Add staff member</h3>
                <p class="text-sm text-white/40 mt-1">Create a brand-new back-office account and pick its role.</p>
            </div>
            <button type="button" @click="close()" class="text-white/30 hover:text-white"><i class="fas fa-times"></i></button>
        </div>

        <form method="POST" action="{{ route('admin.staff.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-white/40 uppercase mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name') }}" required x-ref="nameInput"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                @error('name')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-white/40 uppercase mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-white/40 uppercase mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                    @error('password')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/40 uppercase mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" required
                           class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-white/40 uppercase mb-1">Role</label>
                    <select name="role_id" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="">Select a role…</option>
                        @foreach($adminRoles as $role)
                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/40 uppercase mb-1">Status</label>
                    <select name="status" required class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none">
                        <option value="active" {{ old('status') === 'inactive' ? '' : 'selected' }}>Active</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" @click="close()" class="px-4 py-2 bg-white/5 text-white/70 rounded-xl text-sm hover:bg-white/10">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700 transition">
                    <i class="fas fa-user-plus mr-2"></i>Create staff member
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function addStaff() {
    return {
        open: false,
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.nameInput && this.$refs.nameInput.focus());
        },
        close() {
            this.open = false;
        },
    };
}
function promoteUser() {
    return {
        open: false,
        query: '',
        results: [],
        loading: false,
        selected: null,
        roleId: '',
        _grantTemplate: @json(route('admin.users.admin-access.grant', ['user' => '__USER_ID__'])),
        get grantUrl() {
            return this.selected ? this._grantTemplate.replace('__USER_ID__', this.selected.id) : '#';
        },
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
        },
        close() {
            this.open = false;
            this.clear();
            this.query = '';
            this.results = [];
        },
        clear() {
            this.selected = null;
            this.roleId = '';
        },
        pick(user) {
            this.selected = user;
            this.results = [];
            this.query = '';
        },
        selectedRoleName() {
            if (!this.roleId || !this.$refs.roleSelect) return 'the selected role';
            var opt = this.$refs.roleSelect.options[this.$refs.roleSelect.selectedIndex];
            return opt && opt.value ? opt.text : 'the selected role';
        },
        handleGrantSubmit(e) {
            if (!this.selected || !this.selected.is_admin) {
                return true;
            }
            return window.themedConfirmSubmit(e.target, {
                title: 'Update admin role?',
                message: this.selected.name + ' already has back-office admin access. Granting will change their role to ' + this.selectedRoleName() + '.',
                confirmText: 'Update role',
                confirmIcon: 'fa-user-shield',
                iconClass: 'fa-user-shield'
            });
        },
        async search() {
            const term = this.query.trim();
            if (term.length < 2) { this.results = []; return; }
            this.loading = true;
            try {
                const url = '{{ route('admin.staff.search-users') }}?q=' + encodeURIComponent(term);
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const body = await res.json();
                this.results = body.data || [];
            } catch (e) {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush
@endif
@endsection
