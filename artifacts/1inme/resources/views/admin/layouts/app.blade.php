<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    @include('common.partials.default-icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    @include('common.partials.theme-styles')
    <style>
        /* ============ Shared sidebar shell (mirrors user layout v3) ============ */
        .sidebar-v2 { transition: width 0.35s cubic-bezier(0.4,0,0.2,1), transform 0.35s cubic-bezier(0.4,0,0.2,1); }
        .main-content-v2 { transition: margin-left 0.35s cubic-bezier(0.4,0,0.2,1); }

        .sidebar-v2 .nav-label,
        .sidebar-v2 .logo-text,
        .sidebar-v2 .user-info,
        .sidebar-v2 .section-header { transition: opacity .2s ease, max-height .2s ease; }
        .sidebar-v2.collapsed .nav-label,
        .sidebar-v2.collapsed .logo-text,
        .sidebar-v2.collapsed .user-info,
        .sidebar-v2.collapsed .section-header {
            opacity: 0; max-height: 0; overflow: hidden;
            pointer-events: none; margin: 0; padding: 0;
        }
        .sidebar-v2.collapsed .sidebar-link {
            justify-content: center; align-items: center;
            padding: 0; height: 44px; width: 44px;
            margin: 2px auto; gap: 0;
        }
        .sidebar-v2.collapsed .sidebar-link i { margin: 0; font-size: 1rem; }
        .sidebar-v2.collapsed .sidebar-link.active::after,
        .sidebar-v2.collapsed .sidebar-link.active::before { display: none !important; }
        .sidebar-v2.collapsed nav { display: flex; flex-direction: column; align-items: center; padding-left: 0 !important; padding-right: 0 !important; }
        .sidebar-v2.collapsed nav > * { width: 100%; display: flex; justify-content: center; }
        .sidebar-v2.collapsed .sidebar-link .nav-icon-wrap { margin: 0 auto; width: 36px; height: 36px; min-width: 36px; }

        .sidebar-shell {
            border-right: 1px solid var(--border-strong);
            box-shadow: 1px 0 0 rgba(0,0,0,.10);
        }
        html.light-mode .sidebar-shell {
            border-right: 1px solid #cbd5e1;
            box-shadow: 1px 0 0 rgba(15,23,42,.04);
        }

        .sidebar-edge-toggle {
            position: absolute; top: 20px; right: -14px;
            width: 28px; height: 28px; border-radius: 8px;
            background: var(--bg-card, #1f1f23);
            border: 1px solid var(--border-strong);
            color: var(--text-primary);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,.30);
            font-size: 11px; z-index: 60;
            transition: all .2s ease;
        }
        .sidebar-edge-toggle:hover { background: #7c3aed; color: #fff; border-color: #7c3aed; transform: scale(1.08); }
        html.light-mode .sidebar-edge-toggle { background: #fff; border: 1px solid #cbd5e1; color: #0f172a; box-shadow: 0 4px 12px rgba(15,23,42,.10); }

        .logout-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 8px;
            border: 1px solid var(--border-strong);
            background: transparent; color: var(--text-muted);
            transition: all .18s ease;
        }
        .logout-btn:hover { background: rgba(239,68,68,.10); border-color: rgba(239,68,68,.45); color: #ef4444; }
        html.light-mode .logout-btn { border-color: #cbd5e1; color: #475569; }
        html.light-mode .logout-btn:hover { background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.40); color: #dc2626; }

        .sidebar-tooltip {
            position: absolute; left: calc(100% + 8px); top: 50%; transform: translateY(-50%);
            padding: 4px 10px; border-radius: 8px;
            font-size: 11px; font-weight: 600;
            white-space: nowrap; pointer-events: none;
            opacity: 0; transition: opacity .15s; z-index: 100;
            background: var(--bg-sidebar); color: var(--text-primary);
            border: 1px solid var(--border-subtle);
            box-shadow: 0 4px 20px rgba(0,0,0,.30);
        }
        .sidebar-v2.collapsed .sidebar-link:hover .sidebar-tooltip { opacity: 1; }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen" style="color: var(--text-primary);">
    <div class="bg-mesh"><span class="bloom bloom-pink"></span></div>
    <div class="particles" id="admin-particles"></div>

    <div class="flex h-screen relative z-10 overflow-hidden"
         x-data="{
            sidebarMode: localStorage.getItem('1inme_admin_sidebar') || 'full',
            mobileMenu: false,
            isDesktop: window.innerWidth >= 1024,
            init() {
                const mq = window.matchMedia('(min-width: 1024px)');
                this.isDesktop = mq.matches;
                mq.addEventListener('change', (e) => { this.isDesktop = e.matches; });
            },
            setSidebar(mode) {
                this.sidebarMode = mode;
                localStorage.setItem('1inme_admin_sidebar', mode);
            },
            get sidebarWidth() {
                if (this.sidebarMode === 'full')  return 260;
                if (this.sidebarMode === 'icons') return 72;
                return 0;
            }
         }">

        @include('admin.partials.sidebar')

        {{-- Mobile drawer --}}
        <div x-show="mobileMenu" @click.away="mobileMenu = false" x-cloak
             class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" style="background: var(--overlay-bg);">
            <div class="w-[280px] h-full flex flex-col" style="background: var(--bg-sidebar-mobile);">
                <div class="h-[60px] flex items-center justify-between px-5" style="border-bottom: 1px solid var(--border-subtle);">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-violet-500 to-violet-600 flex items-center justify-center">
                            <span class="text-white text-[10px] font-bold">1</span>
                        </div>
                        <span class="text-base font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-violet-400">ME</span></span>
                        <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(124,58,237,0.1); color: var(--accent-light);">Admin</span>
                    </div>
                    <button @click="mobileMenu = false" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                </div>
                <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div><span>Dashboard</span></a>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') && ! request()->routeIs('admin.users.role-audit-exports.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-users"></i></div><span>Users</span></a>
                    @if(auth('admin')->user()?->isSuperAdmin())
                        <a href="{{ route('admin.users.role-audit-exports.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.role-audit-exports.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-file-csv"></i></div><span>Audit downloads</span></a>
                    @endif
                    <a href="{{ route('admin.adult-moderation.index') }}" class="sidebar-link {{ request()->routeIs('admin.adult-moderation.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-fire"></i></div><span>18+ moderation</span></a>
                    <a href="{{ route('admin.moderation-queue.index') }}" class="sidebar-link {{ request()->routeIs('admin.moderation-queue.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-flag"></i></div><span>Reports & DMCA</span></a>
                    <a href="{{ route('admin.staff.index') }}" class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-shield"></i></div><span>Staff</span></a>
                    <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-key"></i></div><span>Roles</span></a>
                    <a href="{{ route('admin.links.index') }}" class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-link"></i></div><span>All Links</span></a>
                    <a href="{{ route('admin.referrals.index') }}" class="sidebar-link {{ request()->routeIs('admin.referrals.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-gift"></i></div><span>Referrals</span></a>
                    <a href="{{ route('admin.domains.index') }}" class="sidebar-link {{ request()->routeIs('admin.domains.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-globe"></i></div><span>Domains</span></a>
                    <a href="{{ route('admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-tags"></i></div><span>Plans</span></a>
                    <a href="{{ route('admin.addons.index') }}" class="sidebar-link {{ request()->routeIs('admin.addons.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-puzzle-piece"></i></div><span>Addons</span></a>
                    <a href="{{ route('admin.coin-packages.index') }}" class="sidebar-link {{ request()->routeIs('admin.coin-packages.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-coins"></i></div><span>Coin Packages</span></a>
                    <a href="{{ route('admin.wallet-settings.edit') }}" class="sidebar-link {{ request()->routeIs('admin.wallet-settings.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-wallet"></i></div><span>Wallet Settings</span></a>
                    <a href="{{ route('admin.ai-engine.edit') }}" class="sidebar-link {{ request()->routeIs('admin.ai-engine.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-brain"></i></div><span>AI Engine</span></a>
                    <a href="{{ route('admin.ai-usage.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-usage.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-microchip"></i></div><span>AI Usage</span></a>
                    <a href="{{ route('admin.ai-personas.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-personas.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-astronaut"></i></div><span>AI Personas</span></a>
                    <a href="{{ route('admin.ask-coach.index') }}" class="sidebar-link {{ request()->routeIs('admin.ask-coach.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-comment-dots"></i></div><span>Ask Coach</span></a>
                    <a href="{{ route('admin.ai-companions.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-companions.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-robot"></i></div><span>AI Companions</span></a>
                    <a href="{{ route('admin.assets.index') }}" class="sidebar-link {{ request()->routeIs('admin.assets.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-folder-tree"></i></div><span>Asset Vault</span></a>
                </nav>
                @if(!session('impersonate_user_id') && auth()->guard('admin')->user()?->hasUserAccount())
                <div class="p-3" style="border-top: 1px solid var(--border-strong);">
                    <form action="{{ route('admin.switch-to-user') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition-all"
                                style="background: rgba(124,58,237,0.12); border: 1px solid rgba(124,58,237,0.25); color: var(--accent-light);">
                            <i class="fas fa-arrow-right-arrow-left" style="font-size: 11px;"></i>
                            <span>Switch back to user</span>
                        </button>
                    </form>
                </div>
                @endif
            </div>
        </div>

        <div class="flex-1 flex flex-col min-w-0 main-content-v2"
             :style="'margin-left: ' + (isDesktop ? sidebarWidth : 0) + 'px'">
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
                    <div class="mb-4 p-3.5 rounded-xl text-violet-400 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(124,58,237,0.15); background: rgba(124,58,237,0.06);">
                        <i class="fas fa-info-circle"></i> {{ session('info') }}
                    </div>
                @endif

                @yield('content')

                <footer class="mt-10 pt-5 pb-2 text-[11px] flex flex-col sm:flex-row items-center justify-between gap-3"
                        style="border-top: 1px solid var(--border-glass); color: var(--text-dimmed);">
                    <div class="flex items-center gap-2">
                        <span>&copy; {{ date('Y') }} <span style="color: var(--text-muted); font-weight: 600;">1INME</span></span>
                        <span style="color: var(--border-glass-light);">•</span>
                        <span>Admin</span>
                    </div>
                    @include('common.partials.social-links-row', ['justify' => 'justify-end'])
                </footer>
            </main>
        </div>
    </div>

    <script>
    (function(){
        var c = document.getElementById('admin-particles');
        if(!c) return;
        for(var i = 0; i < 12; i++){
            var p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random()*100+'%';
            p.style.animationDuration = (18+Math.random()*25)+'s';
            p.style.animationDelay = Math.random()*20+'s';
            p.style.width = p.style.height = (1+Math.random()*2)+'px';
            p.style.opacity = 0.1+Math.random()*0.25;
            c.appendChild(p);
        }
    })();
    </script>
    @include('common.partials.global-shortcuts')
    @include('partials.voice-assistant')
    @include('user.links.partials.themed-confirm')
    @stack('scripts')
</body>
</html>
