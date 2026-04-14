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
    <div class="bg-mesh"></div>

    <div class="flex min-h-screen relative z-10" x-data="{ sidebarOpen: true, mobileMenu: false }">
        <aside class="w-[260px] flex-shrink-0 hidden lg:flex flex-col fixed inset-y-0 left-0 z-30" style="background: var(--bg-sidebar); backdrop-filter: blur(32px) saturate(1.2); -webkit-backdrop-filter: blur(32px) saturate(1.2); border-right: 1px solid var(--border-subtle);">
            <div class="h-[60px] flex items-center px-5" style="border-bottom: 1px solid var(--border-subtle);">
                <a href="{{ route('user.dashboard') }}" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center shadow-lg" style="box-shadow: 0 4px 12px rgba(139,92,246,0.3);">
                        <span class="text-white text-xs font-bold">1</span>
                    </div>
                    <span class="text-base font-bold tracking-tight">
                        <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                    </span>
                </a>
            </div>

            <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                <a href="{{ route('user.dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-grid-2"></i>
                    <span>Dashboard</span>
                </a>

                <div class="pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: var(--text-faint);">Links</div>

                <a href="{{ route('user.links.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.index') || request()->routeIs('user.links.show') ? 'active' : '' }}">
                    <i class="fas fa-link"></i>
                    <span>All Links</span>
                </a>
                <a href="{{ route('user.links.create') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.create') ? 'active' : '' }}">
                    <i class="fas fa-plus-circle"></i>
                    <span>Create Link</span>
                </a>
                <a href="{{ route('user.qrcode') }}"
                   class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}">
                    <i class="fas fa-qrcode"></i>
                    <span>QR Codes</span>
                </a>

                <div class="pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: var(--text-faint);">Manage</div>

                <a href="{{ route('user.projects.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}">
                    <i class="fas fa-folder"></i>
                    <span>Projects</span>
                </a>
                <a href="{{ route('user.pixels.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}">
                    <i class="fas fa-bullseye"></i>
                    <span>Tracking Pixels</span>
                </a>

                <div class="pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.12em]" style="color: var(--text-faint);">Account</div>

                <a href="{{ route('user.profile.edit') }}"
                   class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
            </nav>

            <div class="mx-3 mb-3 p-3 rounded-xl" style="background: linear-gradient(135deg, rgba(139,92,246,0.08), rgba(168,85,247,0.04)); border: 1px solid rgba(139,92,246,0.12);">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-gem text-purple-400 text-xs"></i>
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">{{ auth()->user()->plan->name ?? 'Free' }} Plan</span>
                </div>
                <p class="text-[10px] mb-2.5 leading-relaxed" style="color: var(--text-dimmed);">Unlock advanced analytics, custom domains & more.</p>
                <a href="#" class="block text-center text-[10px] font-bold uppercase tracking-wider py-1.5 rounded-lg text-purple-400 transition-all hover:text-white hover:bg-purple-600" style="background: rgba(139,92,246,0.1);">
                    Upgrade
                </a>
            </div>

            <div class="p-3" style="border-top: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center text-white text-xs font-bold shadow-md" style="box-shadow: 0 2px 8px rgba(139,92,246,0.25);">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ auth()->user()->name }}</p>
                        <p class="text-[10px] truncate" style="color: var(--text-dimmed);">{{ auth()->user()->email }}</p>
                    </div>
                    <form action="{{ route('user.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="p-1.5 rounded-md hover:text-red-400 transition-colors" style="color: var(--text-dimmed);" title="Logout">
                            <i class="fas fa-sign-out-alt text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 lg:ml-[260px]">
            <header class="h-[60px] flex items-center justify-between px-5 sticky top-0 z-20"
                    style="border-bottom: 1px solid var(--border-subtle); background: var(--bg-header); backdrop-filter: blur(24px) saturate(1.2); -webkit-backdrop-filter: blur(24px) saturate(1.2);">
                <div class="flex items-center gap-3">
                    <button @click="mobileMenu = !mobileMenu" class="lg:hidden hover:opacity-80 p-1" style="color: var(--text-muted);">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-semibold" style="color: var(--text-primary);">@yield('title', 'Dashboard')</span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    @if(session('impersonate_user_id'))
                    <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-2.5 py-1 rounded-lg text-[10px] font-semibold">
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
                       class="btn-primary hidden sm:inline-flex text-xs py-2">
                        <i class="fas fa-plus text-[10px]"></i> New Link
                    </a>

                    <div x-data="{ open: false }" class="relative lg:hidden">
                        <button @click="open = !open" class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-muted);">
                            <i class="fas fa-ellipsis-v text-xs"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-44 glass rounded-xl py-1 z-50" style="box-shadow: var(--card-shadow);">
                            <a href="{{ route('user.profile.edit') }}" class="block px-3 py-2 text-xs hover:opacity-80" style="color: var(--text-muted);">Profile</a>
                            <form action="{{ route('user.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2 text-xs text-red-400 hover:bg-white/5">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div x-show="mobileMenu" @click.away="mobileMenu = false" x-cloak
                 class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" style="background: var(--overlay-bg);">
                <div class="w-[280px] h-full flex flex-col" style="background: var(--bg-sidebar-mobile); backdrop-filter: blur(32px);">
                    <div class="h-[60px] flex items-center justify-between px-5" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center">
                                <span class="text-white text-[10px] font-bold">1</span>
                            </div>
                            <span class="text-base font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span></span>
                        </div>
                        <button @click="mobileMenu = false" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                        <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"><i class="fas fa-grid-2"></i> Dashboard</a>
                        <a href="{{ route('user.links.index') }}" class="sidebar-link {{ request()->routeIs('user.links.*') ? 'active' : '' }}"><i class="fas fa-link"></i> All Links</a>
                        <a href="{{ route('user.links.create') }}" class="sidebar-link"><i class="fas fa-plus-circle"></i> Create Link</a>
                        <a href="{{ route('user.qrcode') }}" class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}"><i class="fas fa-qrcode"></i> QR Codes</a>
                        <a href="{{ route('user.projects.index') }}" class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"><i class="fas fa-folder"></i> Projects</a>
                        <a href="{{ route('user.pixels.index') }}" class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"><i class="fas fa-bullseye"></i> Pixels</a>
                        <a href="{{ route('user.profile.edit') }}" class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}"><i class="fas fa-user-circle"></i> Profile</a>
                    </nav>
                    <div class="p-3" style="border-top: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2 mb-2">
                            @include('common.partials.theme-toggle')
                            <span class="text-[10px]" style="color: var(--text-dimmed);">Theme</span>
                        </div>
                    </div>
                </div>
            </div>

            <main class="flex-1 p-5 lg:p-6 overflow-y-auto">
                @if(session('success'))
                    <div class="mb-4 p-3.5 rounded-xl text-emerald-400 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(16,185,129,0.15); background: rgba(16,185,129,0.06);">
                        <i class="fas fa-check-circle"></i> {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-3.5 rounded-xl text-red-400 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(239,68,68,0.15); background: rgba(239,68,68,0.06);">
                        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    </div>
                @endif
                @if(session('info'))
                    <div class="mb-4 p-3.5 rounded-xl text-purple-400 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(139,92,246,0.15); background: rgba(139,92,246,0.06);">
                        <i class="fas fa-info-circle"></i> {{ session('info') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
