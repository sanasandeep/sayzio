<header class="h-[60px] flex items-center justify-between px-5 sticky top-0 z-20 flex-shrink-0"
        style="border-bottom: 1px solid var(--border-subtle); background: var(--bg-header); backdrop-filter: none; -webkit-backdrop-filter: none;">
    <div class="flex items-center gap-3">
        <button @click="mobileMenu = !mobileMenu" class="lg:hidden hover:opacity-80 p-1" style="color: var(--text-muted);">
            <i class="fas fa-bars"></i>
        </button>
        <div class="flex items-center gap-2 text-sm">
            <span class="font-semibold" style="color: var(--text-primary);">@yield('page-title', 'Dashboard')</span>
        </div>
    </div>

    <div class="flex items-center gap-2">
        @if(session('impersonate_user_id'))
            <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-2.5 py-1 rounded-lg text-[10px] font-semibold">
                <i class="fas fa-user-secret"></i>
                <span>Impersonating user</span>
                <form action="{{ route('admin.users.stop-impersonation') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="ml-1 text-yellow-300 hover:text-yellow-200 font-bold">Stop</button>
                </form>
            </div>
        @endif

        <div class="hidden lg:block">
            @include('common.partials.theme-toggle')
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="flex items-center gap-2 transition-colors hover:opacity-80" style="color: var(--text-muted);">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold" style="background: rgba(124,58,237,0.1); border: 1px solid rgba(124,58,237,0.15); color: var(--accent-light);">
                    {{ strtoupper(substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1)) }}
                </div>
                <i class="fas fa-chevron-down text-[10px]" style="color: var(--text-faint);"></i>
            </button>

            <div x-show="open" @click.away="open = false" x-cloak
                 class="absolute right-0 mt-2 w-44 glass rounded-xl py-1 z-50" style="box-shadow: var(--card-shadow);">
                <form action="{{ route('admin.logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 text-xs text-red-400 hover:bg-white/5 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
