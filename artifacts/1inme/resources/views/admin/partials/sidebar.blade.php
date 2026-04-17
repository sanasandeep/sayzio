<aside class="w-[260px] flex-shrink-0 hidden lg:flex flex-col fixed inset-y-0 left-0 z-30" style="background: var(--bg-sidebar); backdrop-filter: none; -webkit-backdrop-filter: none; border-right: 1px solid var(--border-subtle);">
    <div class="h-[60px] flex items-center px-5" style="border-bottom: 1px solid var(--border-subtle);">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-violet-600 flex items-center justify-center shadow-lg" style="box-shadow: 0 4px 12px rgba(124,58,237,0.3);">
                <span class="text-white text-xs font-bold">1</span>
            </div>
            <span class="text-base font-bold tracking-tight">
                <span style="color: var(--text-primary);">1IN</span><span class="text-violet-400">ME</span>
            </span>
        </a>
        <span class="ml-2 text-[8px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded" style="background: rgba(124,58,237,0.1); color: var(--accent-light); border: 1px solid rgba(124,58,237,0.15);">Admin</span>
    </div>

    <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
        <a href="{{ route('admin.dashboard') }}"
           class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <div class="pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: var(--text-faint);">Management</div>

        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="fas fa-users"></i>
            <span>Users</span>
        </a>

        <a href="{{ route('admin.staff.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield"></i>
            <span>Staff</span>
        </a>

        <a href="{{ route('admin.roles.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <i class="fas fa-key"></i>
            <span>Roles & Permissions</span>
        </a>

        <a href="{{ route('admin.links.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}">
            <i class="fas fa-link"></i>
            <span>All Links</span>
        </a>

        <div class="pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: var(--text-faint);">Settings</div>

        <a href="{{ route('admin.plans.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
            <i class="fas fa-tags"></i>
            <span>Plans</span>
        </a>

        <a href="{{ route('admin.templates.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}">
            <i class="fas fa-layer-group"></i>
            <span>Templates</span>
        </a>

        <a href="{{ route('admin.coach-defaults.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.coach-defaults.*') ? 'active' : '' }}">
            <i class="fas fa-wand-magic-sparkles"></i>
            <span>Coach Defaults</span>
        </a>
    </nav>

    <div class="p-3" style="border-top: 1px solid var(--border-subtle);">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shadow-md" style="box-shadow: 0 2px 8px rgba(124,58,237,0.25);">
                {{ strtoupper(substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ auth()->guard('admin')->user()->name ?? '' }}</p>
                <p class="text-[10px] truncate" style="color: var(--text-dimmed);">{{ auth()->guard('admin')->user()->role->name ?? 'Admin' }}</p>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="p-1.5 rounded-md hover:text-red-400 transition-colors" style="color: var(--text-dimmed);" title="Logout">
                    <i class="fas fa-sign-out-alt text-xs"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
