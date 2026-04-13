<aside class="w-64 bg-dark-900 text-dark-300 flex-shrink-0 flex flex-col"
       :class="sidebarOpen ? 'block' : 'hidden lg:block'">
    <div class="h-16 flex items-center px-6 border-b border-dark-700">
        <span class="text-xl font-bold text-white">1INME</span>
        <span class="ml-2 text-xs bg-primary-600 text-white px-2 py-0.5 rounded-full">Admin</span>
    </div>

    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        <a href="{{ route('admin.dashboard') }}"
           class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="fas fa-chart-line w-5"></i>
            <span>Dashboard</span>
        </a>

        <div class="pt-4 pb-2 px-4 text-xs font-semibold uppercase text-dark-500 tracking-wider">Management</div>

        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="fas fa-users w-5"></i>
            <span>Users</span>
        </a>

        <a href="{{ route('admin.staff.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}">
            <i class="fas fa-user-shield w-5"></i>
            <span>Staff</span>
        </a>

        <a href="{{ route('admin.roles.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
            <i class="fas fa-key w-5"></i>
            <span>Roles & Permissions</span>
        </a>

        <a href="{{ route('admin.links.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}">
            <i class="fas fa-link w-5"></i>
            <span>All Links</span>
        </a>

        <div class="pt-4 pb-2 px-4 text-xs font-semibold uppercase text-dark-500 tracking-wider">Settings</div>

        <a href="{{ route('admin.plans.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}">
            <i class="fas fa-tags w-5"></i>
            <span>Plans</span>
        </a>
    </nav>

    <div class="p-4 border-t border-dark-700">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-medium">
                {{ substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-white truncate">{{ auth()->guard('admin')->user()->name ?? '' }}</p>
                <p class="text-xs text-dark-400 truncate">{{ auth()->guard('admin')->user()->role->name ?? 'Admin' }}</p>
            </div>
        </div>
    </div>
</aside>
