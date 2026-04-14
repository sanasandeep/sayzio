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
    @include('common.partials.theme-styles')
</head>
<body class="min-h-screen" style="color: var(--text-primary);">
    <div class="bg-mesh"></div>

    <div class="flex min-h-screen relative z-10" x-data="{ sidebarOpen: true, mobileMenu: false }">
        @include('admin.partials.sidebar')

        <div x-show="mobileMenu" @click.away="mobileMenu = false" x-cloak
             class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" style="background: var(--overlay-bg);">
            <div class="w-[280px] h-full flex flex-col" style="background: var(--bg-sidebar-mobile); backdrop-filter: blur(32px);">
                <div class="h-[60px] flex items-center justify-between px-5" style="border-bottom: 1px solid var(--border-subtle);">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-purple-500 to-violet-600 flex items-center justify-center">
                            <span class="text-white text-[10px] font-bold">1</span>
                        </div>
                        <span class="text-base font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span></span>
                        <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(139,92,246,0.1); color: var(--accent-light);">Admin</span>
                    </div>
                    <button @click="mobileMenu = false" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                </div>
                <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><i class="fas fa-users"></i> Users</a>
                    <a href="{{ route('admin.staff.index') }}" class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"><i class="fas fa-user-shield"></i> Staff</a>
                    <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"><i class="fas fa-key"></i> Roles</a>
                    <a href="{{ route('admin.links.index') }}" class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}"><i class="fas fa-link"></i> All Links</a>
                    <a href="{{ route('admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}"><i class="fas fa-tags"></i> Plans</a>
                </nav>
                <div class="p-3" style="border-top: 1px solid var(--border-subtle);">
                    <div class="flex items-center gap-2 mb-2">
                        @include('common.partials.theme-toggle')
                        <span class="text-[10px]" style="color: var(--text-dimmed);">Theme</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col min-w-0 lg:ml-[260px]">
            @include('admin.partials.header')

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
