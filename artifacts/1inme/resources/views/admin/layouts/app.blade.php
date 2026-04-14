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
    <div class="bg-glow"></div>

    <div class="flex min-h-screen relative z-10" x-data="{ sidebarOpen: true, mobileMenu: false }">
        @include('admin.partials.sidebar')

        <div x-show="mobileMenu" @click.away="mobileMenu = false" x-cloak
             class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" style="background: var(--overlay-bg);">
            <div class="w-72 h-full flex flex-col" style="background: var(--bg-sidebar-mobile); backdrop-filter: blur(30px);">
                <div class="h-16 flex items-center justify-between px-6" style="border-bottom: 1px solid var(--border-subtle);">
                    <span class="text-xl font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span></span>
                    <button @click="mobileMenu = false" style="color: var(--text-muted);" class="hover:opacity-80"><i class="fas fa-times"></i></button>
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
