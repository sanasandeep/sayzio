<aside class="w-64 flex-shrink-0 hidden lg:flex flex-col"
       style="background: var(--bg-sidebar); backdrop-filter: blur(30px); border-right: 1px solid var(--border-subtle);">
    <div class="h-16 flex items-center px-6" style="border-bottom: 1px solid var(--border-subtle);">
        <a href="{{ route('admin.dashboard') }}" class="text-xl font-bold tracking-tight">
            <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
        </a>
        <span class="ml-2 text-[10px] bg-purple-500/20 text-purple-400 px-2 py-0.5 rounded-full font-semibold border border-purple-500/20">ADMIN</span>
    </div>

    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        <a href="{{ route('admin.dashboard') }}"
           class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-chart-line w-5 text-center"></i>
            <span>Dashboard</span>
        </a>

        <div class="pt-5 pb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Management</div>

        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="fas fa-users w-5 text-center"></i>
            <span>Users</span>
        </a>

        <a href="{{ route('admin.staff.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield w-5 text-center"></i>
            <span>Staff</span>
        </a>

        <a href="{{ route('admin.roles.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <i class="fas fa-key w-5 text-center"></i>
            <span>Roles & Permissions</span>
        </a>

        <a href="{{ route('admin.links.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}">
            <i class="fas fa-link w-5 text-center"></i>
            <span>All Links</span>
        </a>

        <div class="pt-5 pb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Settings</div>

        <a href="{{ route('admin.plans.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
            <i class="fas fa-tags w-5 text-center"></i>
            <span>Plans</span>
        </a>
    </nav>

    <div class="p-4" style="border-top: 1px solid var(--border-subtle);">
        <div class="flex items-center gap-3 mb-3">
            @include('common.partials.theme-toggle')
            <span class="text-xs" style="color: var(--text-dimmed);">Theme</span>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center text-white text-sm font-bold shadow-lg shadow-purple-500/20">
                {{ strtoupper(substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ auth()->guard('admin')->user()->name ?? '' }}</p>
                <p class="text-xs truncate" style="color: var(--text-dimmed);">{{ auth()->guard('admin')->user()->role->name ?? 'Admin' }}</p>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST">
                @csrf
                <button type="submit" class="hover:text-red-400 transition-colors" style="color: var(--text-dimmed);" title="Logout">
                    <i class="fas fa-sign-out-alt text-sm"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
