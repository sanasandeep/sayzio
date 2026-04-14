<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
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
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Space Grotesk', system-ui, sans-serif; background: #0f0a1a; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.08); }
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 1rem; font-size: 0.875rem; border-radius: 0.75rem; transition: all 0.2s; color: rgba(255,255,255,0.5); }
        .sidebar-link:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.9); }
        .sidebar-link.active { background: rgba(124,58,237,0.2); color: #a855f7; border: 1px solid rgba(124,58,237,0.3); }
        .bg-glow { position: fixed; top: -200px; right: -200px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(124,58,237,0.08) 0%, transparent 70%); pointer-events: none; z-index: 0; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
    </style>
</head>
<body class="min-h-screen text-white">
    <div class="bg-glow"></div>

    <div class="flex min-h-screen relative z-10" x-data="{ sidebarOpen: true, mobileMenu: false }">
        @include('admin.partials.sidebar')

        <div x-show="mobileMenu" @click.away="mobileMenu = false" x-cloak
             class="lg:hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm">
            <div class="w-72 h-full flex flex-col" style="background: rgba(15,10,26,0.95); backdrop-filter: blur(30px);">
                <div class="h-16 flex items-center justify-between px-6 border-b border-white/5">
                    <span class="text-xl font-bold"><span class="text-white">1IN</span><span class="text-purple-400">ME</span></span>
                    <button @click="mobileMenu = false" class="text-white/50 hover:text-white"><i class="fas fa-times"></i></button>
                </div>
                <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-chart-line w-5 text-center"></i> Dashboard</a>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><i class="fas fa-users w-5 text-center"></i> Users</a>
                    <a href="{{ route('admin.staff.index') }}" class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"><i class="fas fa-user-shield w-5 text-center"></i> Staff</a>
                    <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"><i class="fas fa-key w-5 text-center"></i> Roles</a>
                    <a href="{{ route('admin.links.index') }}" class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}"><i class="fas fa-link w-5 text-center"></i> All Links</a>
                    <a href="{{ route('admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}"><i class="fas fa-tags w-5 text-center"></i> Plans</a>
                </nav>
            </div>
        </div>

        <div class="flex-1 flex flex-col min-w-0">
            @include('admin.partials.header')

            <main class="flex-1 p-6 overflow-y-auto">
                @if(session('success'))
                    <div class="mb-4 p-4 rounded-xl text-green-400 text-sm flex items-center gap-3" style="border: 1px solid rgba(34,197,94,0.2); background: rgba(34,197,94,0.06);">
                        <i class="fas fa-check-circle"></i> {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-4 rounded-xl text-red-400 text-sm flex items-center gap-3" style="border: 1px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.06);">
                        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                    </div>
                @endif
                @if(session('info'))
                    <div class="mb-4 p-4 rounded-xl text-purple-400 text-sm flex items-center gap-3" style="border: 1px solid rgba(168,85,247,0.2); background: rgba(168,85,247,0.06);">
                        <i class="fas fa-info-circle"></i> {{ session('info') }}
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
