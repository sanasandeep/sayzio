<header class="h-16 bg-white border-b border-dark-200 flex items-center justify-between px-6 flex-shrink-0">
    <div class="flex items-center gap-4">
        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-dark-500 hover:text-dark-700">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <h1 class="text-lg font-semibold text-dark-800">@yield('page-title', 'Dashboard')</h1>
    </div>

    <div class="flex items-center gap-4">
        @if(session('impersonate_user_id'))
            <div class="flex items-center gap-2 bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-1.5 rounded-lg text-sm">
                <i class="fas fa-user-secret"></i>
                <span>Impersonating user</span>
                <form action="{{ route('admin.users.stop-impersonation') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="ml-2 text-yellow-600 hover:text-yellow-800 font-medium">Stop</button>
                </form>
            </div>
        @endif

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 text-dark-600 hover:text-dark-800">
                <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-medium">
                    {{ substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1) }}
                </div>
                <i class="fas fa-chevron-down text-xs"></i>
            </button>

            <div x-show="open" @click.away="open = false" x-cloak
                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-dark-200 py-1 z-50">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
