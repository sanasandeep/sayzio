<header class="h-16 flex items-center justify-between px-6 flex-shrink-0"
        style="border-bottom: 1px solid var(--border-subtle); background: var(--bg-header); backdrop-filter: blur(20px);">
    <div class="flex items-center gap-4">
        <button @click="mobileMenu = !mobileMenu" class="lg:hidden hover:opacity-80" style="color: var(--text-muted);">
            <i class="fas fa-bars text-lg"></i>
        </button>
        <h1 class="text-base font-semibold" style="color: var(--text-secondary);">@yield('page-title', 'Dashboard')</h1>
    </div>

    <div class="flex items-center gap-4">
        @if(session('impersonate_user_id'))
            <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-3 py-1.5 rounded-lg text-xs font-medium">
                <i class="fas fa-user-secret"></i>
                <span>Impersonating user</span>
                <form action="{{ route('admin.users.stop-impersonation') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="ml-2 text-yellow-300 hover:text-yellow-200 font-bold">Stop</button>
                </form>
            </div>
        @endif

        <div class="hidden lg:block">
            @include('common.partials.theme-toggle')
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 transition-colors hover:opacity-80" style="color: var(--text-muted);">
                <div class="w-8 h-8 rounded-xl bg-purple-500/20 border border-purple-500/30 text-purple-400 flex items-center justify-center text-sm font-bold">
                    {{ strtoupper(substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1)) }}
                </div>
                <i class="fas fa-chevron-down text-xs" style="color: var(--text-dimmed);"></i>
            </button>

            <div x-show="open" @click.away="open = false" x-cloak
                 class="absolute right-0 mt-2 w-48 glass rounded-xl py-1 z-50" style="box-shadow: var(--card-shadow);">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-white/5 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
