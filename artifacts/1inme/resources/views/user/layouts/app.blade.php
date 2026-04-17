<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Space Grotesk', 'system-ui', 'sans-serif'] },
                    colors: {
                        primary: { 50:'#eff6ff',100:'#dbeafe',200:'#bfdbfe',300:'#c4b5fd',400:'#a78bfa',500:'#8b5cf6',600:'#7c3aed',700:'#6d28d9',800:'#5b21b6',900:'#4c1d95' },
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
        .header-glow { display: none; }

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
            border-color: rgba(124,58,237,0.3);
            box-shadow: 0 0 0 3px rgba(124,58,237,0.08);
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
            box-shadow: 0 0 6px rgba(124,58,237,0.6);
        }

        .header-breadcrumb {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            min-width: 0;
        }
        .header-breadcrumb .bc-sep {
            color: var(--text-faint);
            font-size: 9px;
            flex-shrink: 0;
        }
        .header-breadcrumb .bc-current {
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.01em;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .header-breadcrumb .bc-parent {
            color: var(--text-faint);
            transition: color 0.2s;
            text-decoration: none;
            white-space: nowrap;
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

        .sidebar-v2.collapsed .sidebar-link .nav-icon-wrap {
            width: 36px;
            height: 36px;
            min-width: 36px;
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

        /* ============ Reusable Page Hero (unified across all user pages) ============ */
        .page-hero {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }
        html.light-mode .page-hero {
            background: #ffffff;
            box-shadow: 0 1px 2px rgba(15,23,42,0.04);
        }
        .page-hero::after { display: none; }
        .page-hero::before { display: none; }
        .page-hero > * { position: relative; z-index: 1; }
        /* ----- Back chip ----- */
        .hero-back {
            width: 36px; height: 36px; border-radius: 12px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border-glass-light);
            color: var(--text-secondary);
            font-size: 12px; flex-shrink: 0;
            transition: all .18s ease;
            text-decoration: none;
        }
        .hero-back:hover { background: rgba(124,58,237,0.18); color: #fff; transform: translateX(-2px); border-color: rgba(124,58,237,0.4); }
        html.light-mode .hero-back { background: rgba(255,255,255,0.7); color: #4c1d95; }
        html.light-mode .hero-back:hover { background: rgba(124,58,237,0.14); color: #4c1d95; }

        /* ----- Emblem (favicon / letter avatar) ----- */
        .hero-emblem {
            width: 56px; height: 56px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            background: var(--c-primary-soft);
            color: var(--accent); font-size: 20px;
            position: relative; overflow: hidden; flex-shrink: 0;
        }
        .hero-emblem.has-favicon { background: #fff; padding: 8px; border: 1px solid var(--border-glass); }
        html.light-mode .hero-emblem.has-favicon { background: #fff; }
        .hero-emblem .favicon-img { width: 100%; height: 100%; object-fit: contain; border-radius: 8px; }
        .hero-emblem-letter {
            font-size: 22px; font-weight: 800; line-height: 1;
            letter-spacing: -0.02em;
            color: var(--accent);
        }

        /* ----- Status chips ----- */
        .hero-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 11px; border-radius: 999px;
            font-size: 10.5px; font-weight: 700;
            background: rgba(255,255,255,0.08);
            border: 1px solid var(--border-glass-light);
            color: var(--text-primary);
            backdrop-filter: none;
            text-decoration: none;
            letter-spacing: 0.02em;
        }
        html.light-mode .hero-chip { background: rgba(255,255,255,0.7); color: #0f172a; }
        .hero-chip i { font-size: 9px; }

        /* ----- Title ----- */
        .hero-title {
            font-size: 1.875rem;
            font-weight: 800;
            line-height: 1.12;
            letter-spacing: -0.02em;
        }
        .hero-subtitle {
            font-size: 0.875rem;
            margin-top: 0.4rem;
            color: var(--text-muted);
        }

        /* ----- URL row (short link + copy/open) ----- */
        .hero-url {
            display: inline-flex; align-items: center; gap: 8px;
            margin-top: 10px;
            padding: 6px 10px 6px 12px;
            background: rgba(124,58,237,0.10);
            border: 1px solid rgba(124,58,237,0.22);
            border-radius: 999px;
            max-width: 100%;
            font-size: 12.5px;
        }
        html.light-mode .hero-url { background: rgba(124,58,237,0.07); border-color: rgba(124,58,237,0.20); }
        .hero-url-icon { font-size: 10px; color: #a78bfa; }
        html.light-mode .hero-url-icon { color: #7c3aed; }
        .hero-url-text {
            color: var(--text-primary); font-weight: 600;
            text-decoration: none;
            min-width: 0;
        }
        .hero-url-text:hover { color: #a78bfa; }
        html.light-mode .hero-url-text:hover { color: #6d28d9; }
        .hero-url-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 8px;
            color: var(--text-faint);
            background: transparent; border: none; cursor: pointer;
            text-decoration: none;
            transition: all .15s ease;
            font-size: 10px;
        }
        .hero-url-btn:hover { background: rgba(124,58,237,0.16); color: #ddd6fe; }
        html.light-mode .hero-url-btn:hover { background: rgba(124,58,237,0.12); color: #6d28d9; }

        @media (max-width: 640px) {
            .page-hero { padding: 16px 18px 16px 22px; border-radius: 18px; }
            .hero-emblem { width: 50px; height: 50px; font-size: 18px; }
            .hero-emblem-letter { font-size: 22px; }
            .hero-title { font-size: 1.4rem; }
            .page-hero::after { width: 3px; top: 16px; bottom: 16px; }
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen" style="color: var(--text-primary);">
    <div class="bg-mesh"></div>
    <div class="particles" id="particles"></div>

    <div class="flex h-screen relative z-10 overflow-hidden"
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
               style="background: var(--bg-sidebar); backdrop-filter: none; -webkit-backdrop-filter: none; border-right: 1px solid var(--border-subtle);">

            <div class="flex items-center px-4" :class="sidebarMode === 'icons' ? 'justify-center' : 'justify-between'" style="height: 64px; border-bottom: 1px solid var(--border-subtle);">
                <a href="{{ route('user.dashboard') }}" class="flex items-center gap-2.5 group" :class="sidebarMode === 'icons' ? 'hidden' : ''">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: var(--accent);">
                        <span class="text-white text-sm font-bold">1</span>
                    </div>
                    <span class="text-lg font-bold tracking-tight logo-text">
                        <span style="color: var(--text-primary);">1IN</span><span class="text-violet-400">ME</span>
                    </span>
                </a>
                <template x-if="sidebarMode === 'icons'">
                    <a href="{{ route('user.dashboard') }}" class="group" title="1INME">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: var(--accent);">
                            <span class="text-white text-sm font-bold">1</span>
                        </div>
                    </a>
                </template>
                <button @click="cycleSidebar()" class="sidebar-toggle-btn" :class="sidebarMode === 'icons' ? 'hidden' : ''" title="Toggle sidebar">
                    <i class="fas fa-chevron-left" style="font-size: 10px;"></i>
                </button>
            </div>

            <nav class="flex-1 py-4 overflow-y-auto overflow-x-hidden sidebar-nav-scroll" :class="sidebarMode === 'icons' ? 'px-2' : 'px-3'">
                <a href="{{ route('user.dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"
                   style="--nav-tint:#7c3aed; --nav-tint-soft:rgba(124,58,237,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-house"></i></div>
                    <span class="nav-label">Dashboard</span>
                    <span class="sidebar-tooltip">Dashboard</span>
                </a>

                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Links</div>

                <a href="{{ route('user.links.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.index') || request()->routeIs('user.links.show') ? 'active' : '' }}"
                   style="--nav-tint:#8b5cf6; --nav-tint-soft:rgba(139,92,246,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-link"></i></div>
                    <span class="nav-label">All Links</span>
                    <span class="sidebar-tooltip">All Links</span>
                </a>
                <a href="{{ route('user.links.create') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.create') ? 'active' : '' }}"
                   style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-plus-circle"></i></div>
                    <span class="nav-label">Create Link</span>
                    <span class="sidebar-tooltip">Create Link</span>
                </a>
                <a href="{{ route('user.qr-codes.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.qr-codes.*') || request()->routeIs('user.qrcode*') ? 'active' : '' }}"
                   style="--nav-tint:#6366f1; --nav-tint-soft:rgba(99,102,241,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-qrcode"></i></div>
                    <span class="nav-label">QR Codes</span>
                    <span class="sidebar-tooltip">QR Codes</span>
                </a>
                <a href="{{ route('user.forms.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.forms.*') ? 'active' : '' }}"
                   style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-wpforms"></i></div>
                    <span class="nav-label">Forms</span>
                    <span class="sidebar-tooltip">Forms</span>
                </a>
                <a href="{{ route('user.splash-pages.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.splash-pages.*') ? 'active' : '' }}"
                   style="--nav-tint:#a855f7; --nav-tint-soft:rgba(168,85,247,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-rocket"></i></div>
                    <span class="nav-label">Intros</span>
                    <span class="sidebar-tooltip">Intros</span>
                </a>
                <a href="{{ route('user.integrations.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.integrations.*') ? 'active' : '' }}"
                   style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-plug"></i></div>
                    <span class="nav-label">Integrations</span>
                    <span class="sidebar-tooltip">Integrations</span>
                </a>
                <a href="{{ route('user.events.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.events.*') ? 'active' : '' }}"
                   style="--nav-tint:#a78bfa; --nav-tint-soft:rgba(167,139,250,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-calendar-day"></i></div>
                    <span class="nav-label">Events</span>
                    <span class="sidebar-tooltip">Events calendar</span>
                </a>
                <a href="{{ route('user.calendar.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.calendar.*') ? 'active' : '' }}"
                   style="--nav-tint:#7c3aed; --nav-tint-soft:rgba(124,58,237,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-calendar-alt"></i></div>
                    <span class="nav-label">Calendar Sync</span>
                    <span class="sidebar-tooltip">Calendar Sync</span>
                </a>

                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Manage</div>

                <a href="{{ route('user.projects.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"
                   style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-folder"></i></div>
                    <span class="nav-label">Projects</span>
                    <span class="sidebar-tooltip">Projects</span>
                </a>
                <a href="{{ route('user.pixels.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"
                   style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div>
                    <span class="nav-label">Tracking</span>
                    <span class="sidebar-tooltip">Tracking</span>
                </a>
                <a href="{{ route('user.social-proofs.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.social-proofs.*') ? 'active' : '' }}"
                   style="--nav-tint:#a855f7; --nav-tint-soft:rgba(168,85,247,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-bell"></i></div>
                    <span class="nav-label">Buzz</span>
                    <span class="sidebar-tooltip">Buzz</span>
                </a>
                <a href="{{ route('user.files.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.files.*') ? 'active' : '' }}"
                   style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-cloud-upload-alt"></i></div>
                    <span class="nav-label">My Files</span>
                    <span class="sidebar-tooltip">Files</span>
                </a>
                <a href="{{ route('user.subscribers.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.subscribers.*') ? 'active' : '' }}"
                   style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-users"></i></div>
                    <span class="nav-label">Leads</span>
                    <span class="sidebar-tooltip">Leads</span>
                </a>
                <a href="{{ route('user.verification.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.verification.*') ? 'active' : '' }}"
                   style="--nav-tint:#3b82f6; --nav-tint-soft:rgba(59,130,246,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-check-circle"></i></div>
                    <span class="nav-label">Verification</span>
                    <span class="sidebar-tooltip">Verification</span>
                </a>

                @if(auth()->user()->isSuperAdmin())
                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Super Admin</div>

                <a href="{{ route('user.plans.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.plans.*') ? 'active' : '' }}"
                   style="--nav-tint:#f43f5e; --nav-tint-soft:rgba(244,63,94,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-layer-group"></i></div>
                    <span class="nav-label">Plans</span>
                    <span class="sidebar-tooltip">Plans</span>
                </a>
                <a href="{{ route('user.verification.admin') }}"
                   class="sidebar-link {{ request()->routeIs('user.verification.admin*') ? 'active' : '' }}"
                   style="--nav-tint:#f97316; --nav-tint-soft:rgba(249,115,22,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-user-check"></i></div>
                    <span class="nav-label">Verify Requests</span>
                    <span class="sidebar-tooltip">Verify Requests</span>
                </a>
                @endif

                <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Account</div>

                <a href="{{ route('user.profile.edit') }}"
                   class="sidebar-link {{ request()->routeIs('user.profile.*') ? 'active' : '' }}"
                   style="--nav-tint:#a78bfa; --nav-tint-soft:rgba(167,139,250,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-user-circle"></i></div>
                    <span class="nav-label">Profile</span>
                    <span class="sidebar-tooltip">Profile</span>
                </a>
            </nav>

            <div class="mx-3 mb-3" x-show="sidebarMode === 'full'" x-cloak x-transition.opacity>
                <div class="upgrade-card">
                    <div class="relative z-10 upgrade-inner">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-6 h-6 rounded-lg bg-violet-500 flex items-center justify-center">
                                <i class="fas fa-gem text-white text-[8px]"></i>
                            </div>
                            <span class="text-xs font-bold" style="color: var(--text-primary);">{{ auth()->user()->plan->name ?? 'Free' }} Plan</span>
                        </div>
                        <p class="text-[10px] mb-3 leading-relaxed" style="color: var(--text-dimmed);">Unlock analytics, custom domains & more.</p>
                        <a href="#" class="block text-center text-[10px] font-bold uppercase tracking-wider py-2 rounded-lg text-white transition-all bg-violet-600 hover:shadow-lg hover:shadow">
                            Upgrade
                        </a>
                    </div>
                </div>
            </div>

            <div class="px-3 py-3" style="border-top: 1px solid var(--border-subtle);">
                <div class="flex items-center" :class="sidebarMode === 'icons' ? 'justify-center' : 'gap-3'">
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
                <button @click="setSidebar(sidebarMode === 'icons' ? 'full' : 'icons')"
                        class="w-full mt-2 flex items-center justify-center gap-2 py-2 rounded-lg text-[11px] font-medium transition-all hover:bg-white/5"
                        style="color: var(--text-faint); border: 1px solid var(--border-subtle);">
                    <i class="fas" :class="sidebarMode === 'icons' ? 'fa-angles-right' : 'fa-angles-left'" style="font-size: 10px;"></i>
                    <span class="nav-label" x-text="sidebarMode === 'icons' ? '' : 'Collapse'"></span>
                </button>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 min-h-0 main-content-v2"
             :style="'margin-left:' + (isDesktop ? sidebarWidth : 0) + 'px'">

            <header class="h-16 flex-shrink-0 flex items-center justify-between px-4 lg:px-6 z-20 header-v2 relative"
                    style="background: var(--bg-header); backdrop-filter: none; -webkit-backdrop-filter: none; border-bottom: 1px solid var(--border-subtle);">
                <div class="header-glow"></div>

                <div class="flex items-center gap-3 min-w-0">
                    <button @click="mobileMenu = !mobileMenu" class="lg:hidden header-icon-btn flex-shrink-0" style="width: 34px; height: 34px;">
                        <i class="fas fa-bars" style="font-size: 13px;"></i>
                    </button>

                    <button @click="setSidebar('full')" class="hidden lg:flex header-icon-btn flex-shrink-0" x-show="sidebarMode === 'hidden'" x-cloak title="Expand sidebar"
                            style="width: 34px; height: 34px;">
                        <i class="fas fa-angles-right" style="font-size: 11px;"></i>
                    </button>

                    <div class="header-breadcrumb min-w-0">
                        @hasSection('breadcrumb_parent')
                            <a href="@yield('breadcrumb_parent_url', '#')" class="bc-parent hidden sm:inline">@yield('breadcrumb_parent')</a>
                            <i class="fas fa-chevron-right bc-sep hidden sm:inline"></i>
                        @endif
                        <span class="bc-current truncate">@yield('title', 'Dashboard')</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    @if(session('impersonate_user_id'))
                    <div class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 px-2.5 py-1 rounded-lg text-[10px] font-semibold">
                        <i class="fas fa-user-secret"></i>
                        <span class="hidden sm:inline">Admin viewing</span>
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

                    <div class="hidden lg:flex items-center">
                        @include('common.partials.theme-toggle')
                    </div>

                    <button class="header-icon-btn hidden sm:flex" title="Notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge-dot"></span>
                    </button>

                    <a href="{{ route('user.links.create') }}" class="btn-primary hidden sm:inline-flex items-center gap-1.5 text-xs px-3.5 py-2 whitespace-nowrap">
                        <i class="fas fa-plus" style="font-size: 9px;"></i>
                        <span>New Link</span>
                    </a>

                    <div x-data="{ open: false }" class="relative lg:hidden">
                        <button @click="open = !open" class="header-icon-btn">
                            <i class="fas fa-ellipsis-v text-xs"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak x-transition
                             class="absolute right-0 mt-2 w-48 rounded-xl py-1.5 z-50" style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
                            <div class="px-3 py-2" style="border-bottom: 1px solid var(--border-subtle);">
                                @include('common.partials.theme-toggle')
                            </div>
                            <a href="{{ route('user.profile.edit') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs hover:opacity-80 transition-opacity" style="color: var(--text-muted);">
                                <i class="fas fa-user-circle" style="width: 14px; text-align: center;"></i> Profile
                            </a>
                            <form action="{{ route('user.logout') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-2.5 text-left px-3 py-2 text-xs text-red-400 hover:bg-red-500/5 transition-colors">
                                    <i class="fas fa-sign-out-alt" style="width: 14px; text-align: center;"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <div x-show="mobileMenu" x-cloak
                 class="lg:hidden fixed inset-0 z-50 backdrop-blur-sm" @click.self="mobileMenu = false" style="background: var(--overlay-bg);">
                <div class="w-[280px] h-full flex flex-col" style="background: var(--bg-sidebar-mobile); backdrop-filter: none;">
                    <div class="h-[64px] flex items-center justify-between px-5" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: var(--accent);">
                                <span class="text-white text-[10px] font-bold">1</span>
                            </div>
                            <span class="text-base font-bold"><span style="color: var(--text-primary);">1IN</span><span class="text-violet-400">ME</span></span>
                        </div>
                        <button @click="mobileMenu = false" class="p-1.5 rounded-lg" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                        <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-house"></i></div> <span>Dashboard</span></a>
                        <a href="{{ route('user.links.index') }}" class="sidebar-link {{ request()->routeIs('user.links.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-link"></i></div> <span>All Links</span></a>
                        <a href="{{ route('user.links.create') }}" class="sidebar-link"><div class="nav-icon-wrap"><i class="fas fa-plus-circle"></i></div> <span>Create Link</span></a>
                        <a href="{{ route('user.qrcode') }}" class="sidebar-link {{ request()->routeIs('user.qrcode*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-qrcode"></i></div> <span>QR Codes</span></a>
                        <a href="{{ route('user.projects.index') }}" class="sidebar-link {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-folder"></i></div> <span>Projects</span></a>
                        <a href="{{ route('user.pixels.index') }}" class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div> <span>Tracking</span></a>
                        <a href="{{ route('user.social-proofs.index') }}" class="sidebar-link {{ request()->routeIs('user.social-proofs.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bell"></i></div> <span>Buzz</span></a>
                        <a href="{{ route('user.files.index') }}" class="sidebar-link {{ request()->routeIs('user.files.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-cloud-upload-alt"></i></div> <span>My Files</span></a>
                        <a href="{{ route('user.subscribers.index') }}" class="sidebar-link {{ request()->routeIs('user.subscribers.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-users"></i></div> <span>Leads</span></a>
                        <a href="{{ route('user.verification.index') }}" class="sidebar-link {{ request()->routeIs('user.verification.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-check-circle"></i></div> <span>Verification</span></a>
                        @if(auth()->user()->isSuperAdmin())
                        <div class="pt-3 mt-2" style="border-top: 1px solid var(--border-subtle);">
                            <p class="px-3 pb-1.5 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Super Admin</p>
                            <a href="{{ route('user.plans.index') }}" class="sidebar-link {{ request()->routeIs('user.plans.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-layer-group"></i></div> <span>Plans</span></a>
                            <a href="{{ route('user.verification.admin') }}" class="sidebar-link {{ request()->routeIs('user.verification.admin*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-check"></i></div> <span>Verify Requests</span></a>
                        </div>
                        @endif
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
                        <i class="fas fa-check-circle"></i>
                        <span class="flex-1">{{ session('success') }}</span>
                        @if(session('coach_undo'))
                            <form action="{{ route('user.links.coach-undo') }}" method="POST" class="ml-auto">
                                @csrf
                                <input type="hidden" name="undo_token" value="{{ session('coach_undo') }}">
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                                        style="border: 1px solid rgba(16,185,129,0.3); background: rgba(16,185,129,0.08); color: #6ee7b7;">
                                    <i class="fas fa-rotate-left text-[9px]"></i> Undo
                                </button>
                            </form>
                        @endif
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

                <footer class="mt-10 pt-5 pb-2 text-[11px] flex flex-col sm:flex-row items-center justify-between gap-2"
                        style="border-top: 1px solid var(--border-glass); color: var(--text-dimmed);">
                    <div class="flex items-center gap-2">
                        <span>&copy; {{ date('Y') }} <span style="color: var(--text-muted); font-weight: 600;">1INME</span></span>
                        <span style="color: var(--border-glass-light);">•</span>
                        <span>All rights reserved</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Docs</a>
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Support</a>
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Privacy</a>
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Terms</a>
                    </div>
                </footer>
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
    @stack('scripts')
</body>
</html>
