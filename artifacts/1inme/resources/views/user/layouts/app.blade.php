<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Space Grotesk', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: { 50:'#f5f3ff',100:'#ede9fe',200:'#ddd6fe',300:'#c4b5fd',400:'#a78bfa',500:'#8b5cf6',600:'#7c3aed',700:'#6d28d9',800:'#5b21b6',900:'#4c1d95' },
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen" style="color: var(--text-primary);">
    <div class="bg-glow"></div>
    <div class="bg-glow-2"></div>

    <div class="flex min-h-screen relative z-10" x-data="{ sidebarOpen: true, mobileMenu: false }">
        <aside class="w-64 flex-shrink-0 hidden lg:flex flex-col glass" style="border-right: 1px solid var(--border-subtle); background: var(--bg-sidebar); backdrop-filter: blur(30px);">
            <div class="h-16 flex items-center px-6" style="border-bottom: 1px solid var(--border-subtle);">
                <a href="{{ route('user.dashboard') }}" class="text-xl font-bold tracking-tight">
                    <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                </a>
            </div>

            <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                <a href="{{ route('user.dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-grid-2 w-5 text-center"></i>
                    <span>Dashboard</span>
                </a>

                <div class="pt-5 pb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Links</div>

                <a href="{{ route('user.links.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.index') || request()->routeIs('user.links.show') ? 'active' : '' }}">
                    <i class="fas fa-link w-5 text-center"></i>
                    <span>All Links</span>
                </a>
                <a href="{{ route('user.links.create') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.create') ? 'active' : '' }}">
                    <i class="fas fa-plus w-5 text-center"></i>
                    <span>Create Link</span>
                </a>
                <a href="{{ route('user.qrcode') }}"
                   class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}">
                    <i class="fas fa-qrcode w-5 text-center"></i>
                    <span>QR Codes</span>
                </a>

                <div class="pt-5 pb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Manage</div>

                <a href="{{ route('user.projects.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}">
                    <i class="fas fa-folder w-5 text-center"></i>
                    <span>Projects</span>
                </a>
                <a href="{{ route('user.pixels.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}">
                    <i class="fas fa-bullseye w-5 text-center"></i>
                    <span>Tracking Pixels</span>
                </a>

                <div class="pt-5 pb-2 px-3 text-[10px] font-semibold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Account</div>

                <a href="{{ route('user.profile.edit') }}"
                   class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}">
                    <i class="fas fa-user-circle w-5 text-center"></i>
                    <span>Profile</span>
                </a>
            </nav>

            <div class="p-4" style="border-top: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-3 mb-3">
                    @include('common.partials.theme-toggle')
                    <span class="text-xs" style="color: var(--text-dimmed);">Theme</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 to-purple-700 flex items-center justify-center text-white text-sm font-bold shadow-lg shadow-purple-500/20">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ auth()->user()->name }}</p>
                        <p class="text-xs truncate" style="color: var(--text-dimmed);">{{ auth()->user()->plan->name ?? 'Free' }} Plan</p>
                    </div>
                    <form action="{{ route('user.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="hover:text-red-400 transition-colors" style="color: var(--text-dimmed);" title="Logout">
                            <i class="fas fa-sign-out-alt text-sm"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 flex items-center justify-between px-6"
                    style="border-bottom: 1px solid var(--border-subtle); background: var(--bg-header); backdrop-filter: blur(20px);">
                <div class="flex items-center gap-4">
                    <button @click="mobileMenu = !mobileMenu" class="lg:hidden hover:opacity-80" style="color: var(--text-muted);">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-base font-semibold" style="color: var(--text-secondary);">@yield('title', 'Dashboard')</h1>
                </div>

                <div class="flex items-center gap-3">
                    @if(session('impersonate_user_id'))
                    <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-3 py-1.5 rounded-lg text-xs font-medium">
                        <i class="fas fa-user-secret"></i>
                        <span>Admin viewing</span>
                        <form action="{{ route('user.logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="ml-1 text-yellow-300 hover:text-yellow-200 font-bold">Exit</button>
                        </form>
                    </div>
                    @endif

                    <div class="hidden lg:block">
                        @include('common.partials.theme-toggle')
                    </div>

                    <a href="{{ route('user.links.create') }}"
                       class="hidden sm:inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-xl transition-all hover:shadow-lg hover:shadow-purple-500/20">
                        <i class="fas fa-plus text-xs"></i> New Link
                    </a>

                    <div x-data="{ open: false }" class="relative lg:hidden">
                        <button @click="open = !open" class="w-9 h-9 rounded-xl flex items-center justify-center hover:opacity-80" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-48 glass rounded-xl py-1 z-50" style="box-shadow: var(--card-shadow);">
                            <a href="{{ route('user.profile.edit') }}" class="block px-4 py-2 text-sm hover:opacity-80" style="color: var(--text-muted);">Profile</a>
                            <form action="{{ route('user.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-white/5">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div x-show="mobileMenu" @click.away="mobileMenu = false" x-cloak
                 class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" style="background: var(--overlay-bg);">
                <div class="w-72 h-full flex flex-col" style="background: var(--bg-sidebar-mobile); backdrop-filter: blur(30px);">
                    <div class="h-16 flex items-center justify-between px-6" style="border-bottom: 1px solid var(--border-subtle);">
                        <span class="text-xl font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span></span>
                        <button @click="mobileMenu = false" style="color: var(--text-muted);" class="hover:opacity-80"><i class="fas fa-times"></i></button>
                    </div>
                    <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                        <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"><i class="fas fa-grid-2 w-5 text-center"></i> Dashboard</a>
                        <a href="{{ route('user.links.index') }}" class="sidebar-link {{ request()->routeIs('user.links.*') ? 'active' : '' }}"><i class="fas fa-link w-5 text-center"></i> All Links</a>
                        <a href="{{ route('user.links.create') }}" class="sidebar-link"><i class="fas fa-plus w-5 text-center"></i> Create Link</a>
                        <a href="{{ route('user.qrcode') }}" class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}"><i class="fas fa-qrcode w-5 text-center"></i> QR Codes</a>
                        <a href="{{ route('user.projects.index') }}" class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"><i class="fas fa-folder w-5 text-center"></i> Projects</a>
                        <a href="{{ route('user.pixels.index') }}" class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"><i class="fas fa-bullseye w-5 text-center"></i> Pixels</a>
                        <a href="{{ route('user.profile.edit') }}" class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}"><i class="fas fa-user-circle w-5 text-center"></i> Profile</a>
                    </nav>
                </div>
            </div>

            <main class="flex-1 p-6 overflow-y-auto">
                @if(session('success'))
                    <div class="mb-4 p-4 glass rounded-xl border-green-500/20 text-green-400 text-sm flex items-center gap-3" style="border-color: rgba(34,197,94,0.2); background: rgba(34,197,94,0.06);">
                        <i class="fas fa-check-circle"></i> {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-4 glass rounded-xl text-red-400 text-sm flex items-center gap-3" style="border-color: rgba(239,68,68,0.2); background: rgba(239,68,68,0.06);">
                        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    </div>
                @endif
                @if(session('info'))
                    <div class="mb-4 p-4 glass rounded-xl text-purple-400 text-sm flex items-center gap-3" style="border-color: rgba(168,85,247,0.2); background: rgba(168,85,247,0.06);">
                        <i class="fas fa-info-circle"></i> {{ session('info') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
