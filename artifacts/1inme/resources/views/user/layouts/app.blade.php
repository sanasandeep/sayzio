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
    <style>
        .sidebar-v2 {
            transition: width 0.35s cubic-bezier(0.4, 0, 0.2, 1), transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .main-content-v2 {
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-v2 .nav-label,
        .sidebar-v2 .logo-text,
        .sidebar-v2 .user-info,
        .sidebar-v2 .upgrade-inner,
        .sidebar-v2 .section-header {
            transition: opacity 0.2s ease, max-height 0.2s ease;
        }
        .sidebar-v2.collapsed .nav-label,
        .sidebar-v2.collapsed .logo-text,
        .sidebar-v2.collapsed .user-info,
        .sidebar-v2.collapsed .upgrade-inner,
        .sidebar-v2.collapsed .section-header {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            pointer-events: none;
            margin: 0;
            padding: 0;
        }
        .sidebar-v2.collapsed .sidebar-link {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        .sidebar-v2.collapsed .sidebar-link i {
            margin: 0;
            font-size: 1rem;
        }
        .sidebar-v2.collapsed .sidebar-link.active::before {
            left: 0;
        }

        .sidebar-tooltip {
            position: absolute;
            left: calc(100% + 8px);
            top: 50%;
            transform: translateY(-50%);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            z-index: 100;
            background: var(--bg-sidebar);
            color: var(--text-primary);
            border: 1px solid var(--border-subtle);
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .sidebar-v2.collapsed .sidebar-link:hover .sidebar-tooltip {
            opacity: 1;
        }

        .header-v2 {
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .header-glow {
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(139,92,246,0.3) 30%, rgba(168,85,247,0.5) 50%, rgba(139,92,246,0.3) 70%, transparent);
            opacity: 0.6;
        }

        .header-search-box {
            position: relative;
            transition: all 0.3s ease;
        }
        .header-search-box input {
            background: var(--bg-glass-input);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 7px 12px 7px 34px;
            font-size: 12px;
            color: var(--text-primary);
            outline: none;
            width: 200px;
            transition: all 0.3s ease;
        }
        .header-search-box input::placeholder { color: var(--text-faint); }
        .header-search-box input:focus {
            width: 280px;
            border-color: rgba(139,92,246,0.3);
            box-shadow: 0 0 0 3px rgba(139,92,246,0.08);
            background: var(--bg-glass-input-focus);
        }
        .header-search-box i {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            color: var(--text-faint);
            pointer-events: none;
        }

        .header-icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s ease;
            position: relative;
            background: var(--bg-glass);
            border: 1px solid var(--border-glass);
            color: var(--text-muted);
            font-size: 13px;
        }
        .header-icon-btn:hover {
            background: var(--bg-glass-hover);
            color: var(--text-primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .header-icon-btn .badge-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #8b5cf6;
            box-shadow: 0 0 6px rgba(139,92,246,0.6);
        }

        .header-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }
        .header-breadcrumb .bc-sep {
            color: var(--text-faint);
            font-size: 10px;
        }
        .header-breadcrumb .bc-current {
            font-weight: 600;
            color: var(--text-primary);
        }
        .header-breadcrumb .bc-parent {
            color: var(--text-faint);
            transition: color 0.2s;
        }
        .header-breadcrumb .bc-parent:hover {
            color: var(--text-muted);
        }

        .sidebar-toggle-btn {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s;
            cursor: pointer;
            color: var(--text-faint);
            background: transparent;
            border: none;
        }
        .sidebar-toggle-btn:hover {
            background: var(--bg-glass-hover);
            color: var(--text-primary);
        }

        .nav-icon-wrap {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }
        .sidebar-link:hover .nav-icon-wrap {
            transform: scale(1.08);
        }
        .sidebar-link.active .nav-icon-wrap {
            background: rgba(139,92,246,0.15);
            box-shadow: 0 0 12px rgba(139,92,246,0.15);
        }

        .sidebar-v2.collapsed .sidebar-link .nav-icon-wrap {
            width: 36px;
            height: 36px;
        }

        .user-avatar-ring {
            padding: 2px;
            border-radius: 12px;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa, #7c3aed);
            display: inline-flex;
        }
        .user-avatar-ring .inner {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: var(--bg-sidebar);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: 700;
        }
    </style>
</head>
<body class="min-h-screen" style="color: var(--text-primary);">
    <div class="bg-mesh"></div>
    <div class="particles" id="particles"></div>

    <div class="flex min-h-screen relative z-10"
         x-data="{
            sidebarMode: localStorage.getItem('1inme_sidebar') || 'full',
            mobileMenu: false,
            isDesktop: window.innerWidth >= 1024,
            init() {
                const mq = window.matchMedia('(min-width: 1024px)');
                this.isDesktop = mq.matches;
                mq.addEventListener('change', (e) => { this.isDesktop = e.matches; });
            },
            cycleSidebar() {
                if(this.sidebarMode === 'full') this.sidebarMode = 'icons';
                else if(this.sidebarMode === 'icons') this.sidebarMode = 'hidden';
                else this.sidebarMode = 'full';
                localStorage.setItem('1inme_sidebar', this.sidebarMode);
            },
            setSidebar(mode) {
                this.sidebarMode = mode;
                localStorage.setItem('1inme_sidebar', mode);
            },
            get sidebarWidth() {
                if(this.sidebarMode === 'full') return 260;
                if(this.sidebarMode === 'icons') return 72;
                return 0;
            }
         }">

        <aside class="hidden lg:flex flex-col fixed inset-y-0 left-0 z-30 sidebar-v2"
               :class="sidebarMode === 'icons' ? 'collapsed' : ''"
               :style="'width:' + sidebarWidth + 'px; transform: translateX(' + (sidebarMode === 'hidden' ? '-100%' : '0') + '); pointer-events:' + (sidebarMode === 'hidden' ? 'none' : 'auto')"
               style="background: var(--bg-sidebar); backdrop-filter: blur(40px) saturate(1.4); -webkit-backdrop-filter: blur(40px) saturate(1.4); border-right: 1px solid var(--border-subtle);">

            <div class="flex items-center justify-between px-4" style="height: 64px; border-bottom: 1px solid var(--border-subtle);">
                <a href="{{ route('user.dashboard') }}" class="flex items-center gap-2.5 group">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-purple-500 via-violet-500 to-purple-700 flex items-center justify-center shadow-lg group-hover:shadow-purple-500/40 transition-all duration-500 group-hover:scale-105" style="box-shadow: 0 4px 16px rgba(139,92,246,0.3);">
                        <span class="text-white text-sm font-bold">1</span>
                    </div>
                    <span class="text-lg font-bold tracking-tight logo-text">
                        <span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span>
                    </span>
                </a>
                <div class="flex items-center gap-0.5">
                    <button @click="setSidebar('full')" class="sidebar-toggle-btn" :class="sidebarMode === 'full' ? 'opacity-100' : 'opacity-40'" title="Full sidebar" style="width: 22px; height: 22px;">
                        <i class="fas fa-bars" style="font-size: 9px;"></i>
                    </button>
                    <button @click="setSidebar('icons')" class="sidebar-toggle-btn" :class="sidebarMode === 'icons' ? 'opacity-100' : 'opacity-40'" title="Icons only" style="width: 22px; height: 22px;">
                        <i class="fas fa-grip-lines-vertical" style="font-size: 9px;"></i>
                    </button>
                    <button @click="setSidebar('hidden')" class="sidebar-toggle-btn" :class="sidebarMode === 'hidden' ? 'opacity-100' : 'opacity-40'" title="Hide sidebar" style="width: 22px; height: 22px;">
                        <i class="fas fa-eye-slash" style="font-size: 8px;"></i>
                    </button>
                </div>
            </div>

            <nav class="flex-1 py-4 overflow-y-auto" :class="sidebarMode === 'icons' ? 'px-2' : 'px-3'" style="scrollbar-width: none;">
                <a href="{{ route('user.dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-grid-2"></i></div>
                    <span class="nav-label">Dashboard</span>
                    <span class="sidebar-tooltip">Dashboard</span>
                </a>

                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Links</div>

                <a href="{{ route('user.links.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.index') || request()->routeIs('user.links.show') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-link"></i></div>
                    <span class="nav-label">All Links</span>
                    <span class="sidebar-tooltip">All Links</span>
                </a>
                <a href="{{ route('user.links.create') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.create') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-plus-circle"></i></div>
                    <span class="nav-label">Create Link</span>
                    <span class="sidebar-tooltip">Create Link</span>
                </a>
                <a href="{{ route('user.qrcode') }}"
                   class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-qrcode"></i></div>
                    <span class="nav-label">QR Codes</span>
                    <span class="sidebar-tooltip">QR Codes</span>
                </a>

                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Manage</div>

                <a href="{{ route('user.projects.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-folder"></i></div>
                    <span class="nav-label">Projects</span>
                    <span class="sidebar-tooltip">Projects</span>
                </a>
                <a href="{{ route('user.pixels.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div>
                    <span class="nav-label">Tracking Pixels</span>
                    <span class="sidebar-tooltip">Pixels</span>
                </a>
                <a href="{{ route('user.files.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.files.*') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-cloud-upload-alt"></i></div>
                    <span class="nav-label">My Files</span>
                    <span class="sidebar-tooltip">Files</span>
                </a>

                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Account</div>

                <a href="{{ route('user.profile.edit') }}"
                   class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}">
                    <div class="nav-icon-wrap"><i class="fas fa-user-circle"></i></div>
                    <span class="nav-label">Profile</span>
                    <span class="sidebar-tooltip">Profile</span>
                </a>
            </nav>

            <div class="mx-3 mb-3" x-show="sidebarMode === 'full'" x-cloak x-transition.opacity>
                <div class="upgrade-card">
                    <div class="relative z-10 upgrade-inner">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-6 h-6 rounded-lg bg-gradient-to-br from-purple-400 to-violet-500 flex items-center justify-center">
                                <i class="fas fa-gem text-white text-[8px]"></i>
                            </div>
                            <span class="text-xs font-bold" style="color: var(--text-primary);">{{ auth()->user()->plan->name ?? 'Free' }} Plan</span>
                        </div>
                        <p class="text-[10px] mb-3 leading-relaxed" style="color: var(--text-dimmed);">Unlock analytics, custom domains & more.</p>
                        <a href="#" class="block text-center text-[10px] font-bold uppercase tracking-wider py-2 rounded-lg text-white transition-all bg-gradient-to-r from-purple-600 to-violet-600 hover:shadow-lg hover:shadow-purple-500/20">
                            Upgrade
                        </a>
                    </div>
                </div>
            </div>

            <div class="px-3 py-3" style="border-top: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-3">
                    <div class="user-avatar-ring flex-shrink-0">
                        <div class="inner">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    </div>
                    <div class="flex-1 min-w-0 user-info">
                        <p class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ auth()->user()->name }}</p>
                        <p class="text-[10px] truncate" style="color: var(--text-dimmed);">{{ auth()->user()->email }}</p>
                    </div>
                    <form action="{{ route('user.logout') }}" method="POST" class="user-info">
                        @csrf
                        <button type="submit" class="p-1.5 rounded-lg hover:text-red-400 hover:bg-red-500/10 transition-all" style="color: var(--text-dimmed);" title="Logout">
                            <i class="fas fa-sign-out-alt text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 main-content-v2"
             :style="'margin-left:' + (isDesktop ? sidebarWidth : 0) + 'px'">

            <header class="h-[64px] flex items-center justify-between px-5 sticky top-0 z-20 header-v2 relative"
                    style="background: var(--bg-header); backdrop-filter: blur(40px) saturate(1.4); -webkit-backdrop-filter: blur(40px) saturate(1.4);">
                <div class="header-glow"></div>

                <div class="flex items-center gap-3">
                    <button @click="mobileMenu = !mobileMenu" class="lg:hidden p-1.5 rounded-lg transition-colors" style="color: var(--text-muted);">
                        <i class="fas fa-bars"></i>
                    </button>

                    <button @click="setSidebar('full')" class="hidden lg:flex sidebar-toggle-btn" x-show="sidebarMode === 'hidden'" x-cloak title="Show sidebar">
                        <i class="fas fa-bars" style="font-size: 12px;"></i>
                    </button>

                    <div class="header-breadcrumb hidden sm:flex">
                        @hasSection('breadcrumb_parent')
                            <a href="@yield('breadcrumb_parent_url', '#')" class="bc-parent">@yield('breadcrumb_parent')</a>
                            <i class="fas fa-chevron-right bc-sep"></i>
                        @endif
                        <span class="bc-current">@yield('title', 'Dashboard')</span>
                    </div>
                    <span class="sm:hidden text-sm font-semibold" style="color: var(--text-primary);">@yield('title', 'Dashboard')</span>
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

                    <div class="header-search-box hidden md:block">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search links, projects..." x-data x-on:keydown.enter="if($el.value.trim()) window.location.href='{{ route('user.links.index') }}?search='+encodeURIComponent($el.value.trim())">
                    </div>

                    <div class="hidden lg:block">
                        @include('common.partials.theme-toggle')
                    </div>

                    <button class="header-icon-btn hidden sm:flex" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge-dot"></span>
                    </button>

                    <a href="{{ route('user.links.create') }}" class="btn-primary hidden sm:inline-flex text-xs py-2">
                        <i class="fas fa-plus text-[10px]"></i> New Link
                    </a>

                    <div x-data="{ open: false }" class="relative lg:hidden">
                        <button @click="open = !open" class="header-icon-btn">
                            <i class="fas fa-ellipsis-v text-xs"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 mt-2 w-44 rounded-xl py-1 z-50" style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
                            <a href="{{ route('user.profile.edit') }}" class="block px-3 py-2 text-xs hover:opacity-80" style="color: var(--text-muted);">Profile</a>
                            <form action="{{ route('user.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2 text-xs text-red-400 hover:bg-white/5">Logout</button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div x-show="mobileMenu" x-cloak
                 class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" @click.self="mobileMenu = false" style="background: var(--overlay-bg);">
                <div class="w-[280px] h-full flex flex-col" style="background: var(--bg-sidebar-mobile); backdrop-filter: blur(40px);">
                    <div class="h-[64px] flex items-center justify-between px-5" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-purple-500 via-violet-500 to-purple-700 flex items-center justify-center">
                                <span class="text-white text-[10px] font-bold">1</span>
                            </div>
                            <span class="text-base font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-purple-400">ME</span></span>
                        </div>
                        <button @click="mobileMenu = false" class="p-1.5 rounded-lg" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                        <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-grid-2"></i></div> <span>Dashboard</span></a>
                        <a href="{{ route('user.links.index') }}" class="sidebar-link {{ request()->routeIs('user.links.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-link"></i></div> <span>All Links</span></a>
                        <a href="{{ route('user.links.create') }}" class="sidebar-link"><div class="nav-icon-wrap"><i class="fas fa-plus-circle"></i></div> <span>Create Link</span></a>
                        <a href="{{ route('user.qrcode') }}" class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-qrcode"></i></div> <span>QR Codes</span></a>
                        <a href="{{ route('user.projects.index') }}" class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-folder"></i></div> <span>Projects</span></a>
                        <a href="{{ route('user.pixels.index') }}" class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div> <span>Pixels</span></a>
                        <a href="{{ route('user.profile.edit') }}" class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-circle"></i></div> <span>Profile</span></a>
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
                    <div class="mb-4 p-3.5 rounded-xl text-emerald-400 text-xs font-medium flex items-center gap-2.5 shimmer" style="border: 1px solid rgba(16,185,129,0.15); background: rgba(16,185,129,0.06);">
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

    <script>
    (function(){
        var c = document.getElementById('particles');
        if(!c) return;
        for(var i = 0; i < 15; i++){
            var p = document.createElement('div');
            p.className = 'particle';
            p.style.left = Math.random()*100+'%';
            p.style.animationDuration = (15+Math.random()*25)+'s';
            p.style.animationDelay = Math.random()*20+'s';
            p.style.width = p.style.height = (1+Math.random()*2)+'px';
            p.style.opacity = 0.1+Math.random()*0.3;
            c.appendChild(p);
        }
    })();
    </script>
</body>
</html>
