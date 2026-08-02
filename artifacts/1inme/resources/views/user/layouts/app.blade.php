<!DOCTYPE html>
<html lang="en" class="{{ (($_COOKIE['1inme_theme'] ?? null) === 'dark') ? '' : 'light-mode' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a14">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @include('common.partials.default-icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    @include('common.partials.theme-styles')
    <style>
        /* Single source of truth for the in-app header height (matches the
           header's Tailwind h-16 = 4rem below). Anything that needs to budget
           around the sticky header — e.g. the biolink editor's palette panel
           and device-preview height calcs — should reference this variable so a
           future header-height change keeps everything in lockstep. */
        :root { --app-header-h: 4rem; }
        /* Dashboard ambient wash (very soft blue/cyan, low alpha, no heavy blur aurora) */
        .dashboard-wash {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            background: radial-gradient(circle at 15% 20%, rgba(34, 211, 238, 0.03) 0%, transparent 60%),
                        radial-gradient(circle at 85% 75%, rgba(61, 107, 255, 0.04) 0%, transparent 60%);
        }
        html.light-mode .dashboard-wash {
            background: radial-gradient(circle at 15% 20%, rgba(34, 211, 238, 0.05) 0%, transparent 60%),
                        radial-gradient(circle at 85% 75%, rgba(61, 107, 255, 0.06) 0%, transparent 60%);
        }

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
            align-items: center;
            padding: 0;
            height: 44px;
            width: 44px;
            margin: 2px auto;
            gap: 0;
        }
        .sidebar-v2.collapsed .sidebar-link i {
            margin: 0;
            font-size: 1rem;
        }
        /* Hide the left active accent bar when collapsed — it shifts the icon visually off-center */
        .sidebar-v2.collapsed .sidebar-link.active::after,
        .sidebar-v2.collapsed .sidebar-link.active::before {
            display: none !important;
        }
        /* When collapsed, center every nav item on the sidebar's vertical axis */
        .sidebar-v2.collapsed nav { display: flex; flex-direction: column; align-items: center; padding-left: 0 !important; padding-right: 0 !important; }
        /* Stack children VERTICALLY (flex-direction: column) — a plain
           `display:flex` row here previously laid each collapsible group's
           links out side-by-side inside the 72px sidebar, clipping them out
           of view (Task #5536). align-items centers each 44px icon link. */
        .sidebar-v2.collapsed nav > * { width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; }
        /* Group wrappers (<div x-data>) and their x-show link containers must
           also stack vertically and span the rail so every grouped link shows
           as a centered icon in collapsed mode. */
        .sidebar-v2.collapsed .sidebar-nav-scroll > div,
        .sidebar-v2.collapsed .sidebar-nav-scroll > div > div {
            display: block;
            width: 100%;
        }
        .sidebar-v2.collapsed .sidebar-link .nav-icon-wrap {
            margin: 0 auto;
        }

        /* Sidebar shell: visible right border in both modes */
        /* Dashboard liquid glass utility (sidebar, header, mobile nav) */
        .dash-glass {
            background: rgba(255, 255, 255, 0.04) !important;
            border-color: transparent !important;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.06), inset 1.5px 2px 0 -1px rgba(255, 255, 255, 0.4), inset -1.5px -1.5px 0 -1px rgba(255, 255, 255, 0.2), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.15), inset 0 0 8px 1px rgba(0, 0, 0, 0.2), 0 12px 32px rgba(0, 0, 0, 0.4) !important;
        }
        @supports (backdrop-filter: blur(8px)) {
            .dash-glass {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.01) 100%) !important;
                backdrop-filter: blur(6px) saturate(180%) brightness(1.1) !important;
                -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.1) !important;
            }
        }
        html.light-mode .dash-glass {
            background: rgba(255, 255, 255, 0.15) !important;
            border-color: transparent !important;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.4), inset 1.8px 3px 0 -2px rgba(255, 255, 255, 0.9), inset -2px -2px 0 -2px rgba(255, 255, 255, 0.8), inset -3px -8px 1px -6px rgba(255, 255, 255, 0.6), inset -0.3px -1px 4px 0 rgba(0, 0, 0, 0.05), inset 0 0 8px 1px rgba(0, 0, 0, 0.02), 0 12px 32px rgba(0, 0, 0, 0.08) !important;
        }
        @supports (backdrop-filter: blur(8px)) {
            html.light-mode .dash-glass {
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.25) 0%, rgba(255, 255, 255, 0.1) 100%) !important;
                backdrop-filter: blur(6px) saturate(180%) brightness(1.05) !important;
                -webkit-backdrop-filter: blur(6px) saturate(180%) brightness(1.05) !important;
            }
        }

        .sidebar-shell {
            border-right: none;
            border-radius: 0 1.5rem 1.5rem 0;
        }
        html.light-mode .sidebar-shell {
            border-right: none;
        }

        /* Edge-mounted collapse handle — sits 50% in / 50% out of sidebar */
        .sidebar-edge-toggle {
            position: absolute;
            top: 20px;
            right: -14px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--bg-card, #1f1f23);
            border: 1px solid var(--border-strong);
            color: var(--text-primary);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,.30);
            font-size: 11px;
            z-index: 60;
            transition: all .2s ease;
        }
        .sidebar-edge-toggle:hover {
            background: #3d6bff; color: #fff; border-color: #3d6bff;
            transform: scale(1.08);
        }
        html.light-mode .sidebar-edge-toggle {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            box-shadow: 0 4px 12px rgba(15,23,42,.10);
        }

        /* Logout button — clear outline so it's discoverable */
        .logout-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-strong);
            background: transparent;
            color: var(--text-muted);
            transition: all .18s ease;
        }
        .logout-btn:hover {
            background: rgba(239,68,68,.10);
            border-color: rgba(239,68,68,.45);
            color: #ef4444;
        }
        html.light-mode .logout-btn {
            border-color: #cbd5e1;
            color: #475569;
        }
        html.light-mode .logout-btn:hover {
            background: rgba(239,68,68,.08);
            border-color: rgba(239,68,68,.40);
            color: #dc2626;
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

        /* Collapsible sidebar groups — condense the long tail into scannable headers */
        .sidebar-group-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--text-faint);
            transition: color .18s ease;
        }
        .sidebar-group-toggle:hover { color: var(--text-muted); }
        .sidebar-group-toggle:focus-visible {
            outline: 2px solid rgba(61,107,255,0.5);
            outline-offset: 2px;
            border-radius: 6px;
        }
        .sidebar-group-toggle .grp-chevron {
            font-size: 9px;
            transition: transform .2s ease;
            flex-shrink: 0;
        }
        .sidebar-group-toggle[aria-expanded="true"] .grp-chevron { transform: rotate(180deg); }
        @media (prefers-reduced-motion: reduce) {
            .sidebar-group-toggle,
            .sidebar-group-toggle .grp-chevron { transition: none; }
        }

        .header-v2 {
            transition: margin-left 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        html.light-mode .header-v2 {
            box-shadow: 0 1px 3px rgba(7,20,55,0.04);
        }
        .header-glow { display: none; }

        .header-icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 11px;
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
            box-shadow: 0 6px 16px -4px rgba(0,0,0,0.18);
            border-color: var(--border-glass-light);
        }
        .header-icon-btn .badge-dot {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #5c83ff;
            box-shadow: 0 0 6px rgba(61,107,255,0.6);
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
            background: linear-gradient(135deg, #5c83ff, #90acff, #3d6bff);
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
            border-radius: 16px;
            padding: 22px 28px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }
        html.light-mode .page-hero {
            background: #ffffff;
            box-shadow: var(--card-shadow);
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
        .hero-back:hover { background: rgba(61,107,255,0.18); color: #fff; transform: translateX(-2px); border-color: rgba(61,107,255,0.4); }
        html.light-mode .hero-back { background: rgba(255,255,255,0.7); color: #20367f; }
        html.light-mode .hero-back:hover { background: rgba(61,107,255,0.14); color: #20367f; }

        /* ----- Emblem (favicon / letter avatar) ----- */
        .hero-emblem {
            width: 56px; height: 56px;
            border-radius: 14px;
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
            background: rgba(61,107,255,0.10);
            border: 1px solid rgba(61,107,255,0.22);
            border-radius: 999px;
            max-width: 100%;
            font-size: 12.5px;
        }
        html.light-mode .hero-url { background: rgba(61,107,255,0.07); border-color: rgba(61,107,255,0.20); }
        .hero-url-icon { font-size: 10px; color: #90acff; }
        html.light-mode .hero-url-icon { color: #3d6bff; }
        .hero-url-text {
            color: var(--text-primary); font-weight: 600;
            text-decoration: none;
            min-width: 0;
        }
        .hero-url-text:hover { color: #90acff; }
        html.light-mode .hero-url-text:hover { color: #2342c7; }
        .hero-url-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 8px;
            color: var(--text-faint);
            background: transparent; border: none; cursor: pointer;
            text-decoration: none;
            transition: all .15s ease;
            font-size: 10px;
        }
        .hero-url-btn:hover { background: rgba(61,107,255,0.16); color: #dbe4ff; }
        html.light-mode .hero-url-btn:hover { background: rgba(61,107,255,0.12); color: #2342c7; }

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
<body class="min-h-screen" data-app-layout style="color: var(--text-primary);">
    <div class="dashboard-wash"></div>
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

        <aside class="hidden lg:flex flex-col fixed inset-y-0 left-0 z-30 sidebar-v2 sidebar-shell dash-glass"
               :class="sidebarMode === 'icons' ? 'collapsed' : ''"
               :style="'width:' + sidebarWidth + 'px; transform: translateX(' + (sidebarMode === 'hidden' ? '-100%' : '0') + '); pointer-events:' + (sidebarMode === 'hidden' ? 'none' : 'auto')">

            {{-- Edge-mounted collapse handle (sits 50% in / 50% out of sidebar) --}}
            <button @click="setSidebar(sidebarMode === 'icons' ? 'full' : 'icons')"
                    class="sidebar-edge-toggle"
                    title="Toggle sidebar"
                    aria-label="Toggle sidebar">
                <i class="fas" :class="sidebarMode === 'icons' ? 'fa-chevron-right' : 'fa-chevron-left'"></i>
            </button>

            <div class="flex items-center px-4" :class="sidebarMode === 'icons' ? 'justify-center' : 'justify-start'" style="height: 64px; border-bottom: 1px solid var(--border-strong);">
                <a href="{{ route('user.dashboard') }}" class="flex items-center gap-2.5 group" :class="sidebarMode === 'icons' ? 'hidden' : ''">
                    @include('common.partials.brand-logo', ['height' => 'h-8'])
                </a>
                <template x-if="sidebarMode === 'icons'">
                    <a href="{{ route('user.dashboard') }}" class="group" title="{{ config('app.name', 'Sayzio') }}">
                        @include('common.partials.brand-logo', ['variant' => 'icon', 'height' => 'h-9'])
                    </a>
                </template>
            </div>
            @auth
                @include('user.partials.workspace-switcher')
            @endauth

            @php
                use App\Modules\User\Services\WorkspacePermissions as WP;
                // Resolve once: hide menu entries the active member's role can't reach.
                // Owners and super-admins always pass these checks.
                $__can = [
                    'tasks_view'     => WP::userCan('tasks.view'),
                    'vault_view'     => WP::userCan('vault.view'),
                    'files_view'     => WP::userCan('files.view'),
                    'links_view'     => WP::userCan('links.view'),
                    'links_create'   => WP::userCan('links.create'),
                    'inbox_view'     => WP::userCan('inbox.view'),
                    'posts_view'     => WP::userCan('posts.view'),
                    'followers_view' => WP::userCan('followers.view'),
                    'stats_view'     => WP::userCan('stats.view'),
                    'settings_view'  => WP::userCan('settings.view'),
                    'referrals_view' => WP::userCan('referrals.view'),
                ];
            @endphp
            {{-- overflow:clip (not hidden): browser find-in-page (esp. Safari) can
                 programmatically scroll an overflow:hidden box, blanking the whole
                 nav with no scrollbar to recover it. clip is unscrollable by spec;
                 the onscroll reset is the fallback for engines without clip. --}}
            <nav class="flex-1 relative overflow-hidden" style="overflow:clip"
                 onscroll="this.scrollTop=0;this.scrollLeft=0">
            <div class="absolute inset-0 overflow-y-auto overflow-x-hidden sidebar-nav-scroll py-4" :class="sidebarMode === 'icons' ? 'px-2' : 'px-3'">
                {{-- ========== TOP LEVEL — most-used destinations stay visible ========== --}}
                <a href="{{ route('user.dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"
                   style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-house"></i></div>
                    <span class="nav-label">Dashboard</span>
                    <span class="sidebar-tooltip">Dashboard</span>
                </a>
                @if($__can['links_view'])
                <a href="{{ route('user.links.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.index') || request()->routeIs('user.links.show') ? 'active' : '' }}"
                   style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-link"></i></div>
                    <span class="nav-label">All Links</span>
                    <span class="sidebar-tooltip">All Links</span>
                </a>
                @endif
                @if($__can['links_create'])
                <a href="{{ route('user.links.create') }}"
                   class="sidebar-link {{ request()->routeIs('user.links.create') ? 'active' : '' }}"
                   style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-plus-circle"></i></div>
                    <span class="nav-label">Create Link</span>
                    <span class="sidebar-tooltip">Create Link</span>
                </a>
                @endif
                @if($__can['links_view'])
                <a href="{{ route('user.calendars.mine') }}"
                   class="sidebar-link {{ request()->routeIs('user.calendars.*') ? 'active' : '' }}"
                   style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-calendar-days"></i></div>
                    <span class="nav-label">My Calendar</span>
                    <span class="sidebar-tooltip">My Calendar</span>
                </a>
                @endif
                @if($__can['inbox_view'])
                <a href="{{ route('user.inbox.unified.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.inbox.unified.*') ? 'active' : '' }}"
                   style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-inbox"></i></div>
                    <span class="nav-label">Inbox
                        @php $__topInbox = (new \App\Modules\User\Services\InboxAggregator(auth()->id()))->unreadCount(); @endphp
                        @if($__topInbox)<span class="ml-1 inline-block px-1.5 rounded-full text-[10px] bg-blue-500 text-white">{{ $__topInbox > 99 ? '99+' : $__topInbox }}</span>@endif
                    </span>
                    <span class="sidebar-tooltip">Inbox 2.0, triaged across forms, DMs &amp; sponsorships</span>
                </a>
                @endif
                <a href="{{ route('user.notifications.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.notifications.*') ? 'active' : '' }}"
                   style="--nav-tint:#fbbf24; --nav-tint-soft:rgba(251,191,36,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-bell"></i></div>
                    <span class="nav-label">Notifications
                        @php $__unread = \App\Modules\User\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count(); @endphp
                        @if($__unread)<span class="ml-1 inline-block px-1.5 rounded-full text-[10px] bg-rose-500 text-white">{{ $__unread }}</span>@endif
                    </span>
                    <span class="sidebar-tooltip">Notifications</span>
                </a>
                @if($__can['posts_view'])
                <a href="{{ route('user.stats.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.stats.*') ? 'active' : '' }}"
                   style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
                    <span class="nav-label">Stats</span>
                    <span class="sidebar-tooltip">Stats</span>
                </a>
                @endif
                @if($__can['stats_view'])
                <a href="{{ route('user.visitors.index') }}"
                   class="sidebar-link {{ request()->routeIs('user.visitors.index') ? 'active' : '' }}"
                   style="--nav-tint:#5cc7ff; --nav-tint-soft:rgba(92,199,255,0.12);">
                    <div class="nav-icon-wrap"><i class="fas fa-users"></i></div>
                    <span class="nav-label">Visitors</span>
                    <span class="sidebar-tooltip">Visitors</span>
                </a>
                @endif
                {{-- Task #3848 — the Wallet nav item moved to a compact icon +
                     balance badge in the top header (see the header markup
                     below); it no longer has its own sidebar entry. --}}

                {{-- ========== LINKS & PAGES (collapsible) ========== --}}
                @if($__can['links_view'] || $__can['inbox_view'] || $__can['files_view'])
                @php $grpLinksActive = request()->routeIs('user.qr-codes.*') || request()->routeIs('user.qrcode*') || request()->routeIs('user.forms.*') || request()->routeIs('user.backlinks.*') || request()->routeIs('user.splash-pages.*') || request()->routeIs('user.resume.*') || request()->routeIs('user.projects.*') || request()->routeIs('user.files.*') || request()->routeIs('user.cloud-files.*') || request()->routeIs('user.cloud-oauth.*'); @endphp
                <div x-data="{ open: {{ $grpLinksActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Links &amp; Pages</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['links_view'])
                        <a href="{{ route('user.qr-codes.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.qr-codes.*') || request()->routeIs('user.qrcode*') ? 'active' : '' }}"
                           style="--nav-tint:#6366f1; --nav-tint-soft:rgba(99,102,241,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-qrcode"></i></div>
                            <span class="nav-label">QR Codes</span>
                            <span class="sidebar-tooltip">QR Codes</span>
                        </a>
                        @endif
                        @if($__can['inbox_view'])
                        <a href="{{ route('user.forms.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.forms.*') ? 'active' : '' }}"
                           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-clipboard-list"></i></div>
                            <span class="nav-label">Forms</span>
                            <span class="sidebar-tooltip">Forms</span>
                        </a>
                        @endif
                        @if($__can['links_view'])
                        <a href="{{ route('user.backlinks.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.backlinks.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div>
                            <span class="nav-label">Backlinks</span>
                            <span class="sidebar-tooltip">Backlinks</span>
                        </a>
                        <a href="{{ route('user.splash-pages.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.splash-pages.*') ? 'active' : '' }}"
                           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-rocket"></i></div>
                            <span class="nav-label">Intros</span>
                            <span class="sidebar-tooltip">Intros</span>
                        </a>
                        <a href="{{ route('user.resume.editor') }}"
                           class="sidebar-link {{ request()->routeIs('user.resume.*') ? 'active' : '' }}"
                           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-file-lines"></i></div>
                            <span class="nav-label">AI Resume / Portfolio</span>
                            <span class="sidebar-tooltip">AI Resume / Portfolio</span>
                        </a>
                        @php
                            $__navFolders = workspace_owner()->projects()->orderBy('name')->limit(10)->get(['id', 'name', 'color']);
                        @endphp
                        <div x-data="{ foldersOpen: {{ request()->routeIs('user.projects.*') || request()->filled('project_id') ? 'true' : 'false' }} }">
                            <div class="flex items-center">
                                <a href="{{ route('user.projects.index') }}"
                                   class="sidebar-link flex-1 {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"
                                   style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                                    <div class="nav-icon-wrap"><i class="fas fa-folder"></i></div>
                                    <span class="nav-label">Folders</span>
                                    <span class="sidebar-tooltip">Folders</span>
                                </a>
                                @if($__navFolders->isNotEmpty())
                                <button type="button" @click="foldersOpen = !foldersOpen"
                                        class="nav-label p-1.5 mr-1 rounded-lg text-white/30 hover:text-white/70 hover:bg-white/10"
                                        :aria-expanded="foldersOpen ? 'true' : 'false'" aria-label="Toggle folders list">
                                    <i class="fas fa-chevron-down text-[10px] transition-transform" :class="foldersOpen ? '' : '-rotate-90'"></i>
                                </button>
                                @endif
                            </div>
                            <div x-show="foldersOpen" x-collapse class="nav-label pl-9 pr-2 pb-1 space-y-0.5">
                                @foreach($__navFolders as $__nf)
                                <a href="{{ route('user.links.index', ['project_id' => $__nf->id]) }}"
                                   class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px] {{ request('project_id') == $__nf->id ? 'text-white bg-white/10' : 'text-white/50 hover:text-white hover:bg-white/5' }}">
                                    <i class="fas fa-folder text-xs" style="color: {{ $__nf->color ?: '#3b82f6' }}"></i>
                                    <span class="truncate">{{ $__nf->name }}</span>
                                </a>
                                @endforeach
                                <a href="{{ route('user.projects.create') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px] text-white/40 hover:text-white hover:bg-white/5">
                                    <i class="fas fa-plus text-xs"></i>
                                    <span>New folder</span>
                                </a>
                            </div>
                        </div>
                        @endif
                        @if($__can['files_view'] || $__can['links_view'])
                        @php
                            $__filesLastTab = session('files_last_tab', 'vault');
                            if ($__filesLastTab === 'cloud' && $__can['files_view']) {
                                $filesHref = route('user.cloud-files.index');
                            } elseif ($__can['links_view']) {
                                $filesHref = route('user.files.index');
                            } else {
                                $filesHref = route('user.cloud-files.index');
                            }
                        @endphp
                        <a href="{{ $filesHref }}"
                           class="sidebar-link {{ request()->routeIs('user.files.*') || request()->routeIs('user.cloud-files.*') || request()->routeIs('user.cloud-oauth.*') ? 'active' : '' }}"
                           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-folder-open"></i></div>
                            <span class="nav-label">Files</span>
                            <span class="sidebar-tooltip">Files, your vault &amp; cloud library</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ========== AUDIENCE & COMMUNITY (collapsible) ========== --}}
                @if($__can['inbox_view'] || $__can['followers_view'] || $__can['posts_view'] || $__can['settings_view'])
                @php $grpAudienceActive = request()->routeIs('user.subscribers.*') || request()->routeIs('user.followers.*') || request()->routeIs('user.following.*') || request()->routeIs('user.feed.*') || request()->routeIs('user.posts.*') || request()->routeIs('user.contacts.*') || request()->routeIs('user.dialer.*'); @endphp
                <div x-data="{ open: {{ $grpAudienceActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Audience &amp; Community</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['inbox_view'])
                        <a href="{{ route('user.subscribers.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.subscribers.*') ? 'active' : '' }}"
                           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-users"></i></div>
                            <span class="nav-label">Subscribers</span>
                            <span class="sidebar-tooltip">Subscribers</span>
                        </a>
                        @endif
                        @if($__can['followers_view'])
                        <a href="{{ route('user.followers.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.followers.*') || request()->routeIs('user.following.*') ? 'active' : '' }}"
                           style="--nav-tint:#f472b6; --nav-tint-soft:rgba(244,114,182,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-user-group"></i></div>
                            <span class="nav-label">Followers</span>
                            <span class="sidebar-tooltip">Followers</span>
                        </a>
                        @endif
                        @if($__can['posts_view'])
                        <a href="{{ route('feed.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.feed.*') ? 'active' : '' }}"
                           style="--nav-tint:#34d399; --nav-tint-soft:rgba(52,211,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-stream"></i></div>
                            <span class="nav-label">Feed</span>
                            <span class="sidebar-tooltip">Feed</span>
                        </a>
                        <a href="{{ route('user.posts.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.posts.*') ? 'active' : '' }}"
                           style="--nav-tint:#60a5fa; --nav-tint-soft:rgba(96,165,250,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-pen-to-square"></i></div>
                            <span class="nav-label">My Posts</span>
                            <span class="sidebar-tooltip">My Posts</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])
                        <a href="{{ route('user.leads.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.leads.*') ? 'active' : '' }}"
                           style="--nav-tint:#38bdf8; --nav-tint-soft:rgba(56,189,248,0.12);">
                            <div class="nav-icon-wrap" style="position:relative;"><i class="fas fa-user-plus"></i>
                                @if(($pendingLeadsCount ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full text-[9px] font-bold leading-none text-white ring-2" style="background:#ef4444; --tw-ring-color:var(--sidebar-bg,#0b1020);" title="{{ $pendingLeadsCount }} pending lead(s)">{{ $pendingLeadsCount > 99 ? '99+' : $pendingLeadsCount }}</span>
                                @endif
                            </div>
                            <span class="nav-label">Leads</span>
                            <span class="sidebar-tooltip">Leads</span>
                        </a>
                        <a href="{{ route('user.contacts.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.contacts.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap" style="position:relative;"><i class="fas fa-address-book"></i>
                                @if(($contactsOverdueFollowUps ?? 0) > 0)
                                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full text-[9px] font-bold leading-none text-white ring-2" style="background:#ef4444; --tw-ring-color:var(--sidebar-bg,#0b1020);" title="{{ $contactsOverdueFollowUps }} overdue follow-up(s)">{{ $contactsOverdueFollowUps > 99 ? '99+' : $contactsOverdueFollowUps }}</span>
                                @endif
                            </div>
                            <span class="nav-label">Contacts</span>
                            <span class="sidebar-tooltip">Contacts</span>
                        </a>
                        <a href="{{ route('user.dialer.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.dialer.*') ? 'active' : '' }}"
                           style="--nav-tint:#34d399; --nav-tint-soft:rgba(52,211,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-phone"></i></div>
                            <span class="nav-label">Dialer</span>
                            @include('common.partials.soon-badge', ['feature' => 'dialer'])
                            <span class="sidebar-tooltip">Dialer</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ========== MONETIZATION (collapsible) ========== --}}
                @if($__can['posts_view'])
                @php $grpMonetActive = request()->routeIs('user.payouts.*') || request()->routeIs('user.adult-content.*') || request()->routeIs('user.monetization.*'); @endphp
                <div x-data="{ open: {{ $grpMonetActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Monetization</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.payouts.show') }}"
                           class="sidebar-link {{ request()->routeIs('user.payouts.*') || request()->routeIs('user.adult-content.*') ? 'active' : '' }}"
                           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-sack-dollar"></i></div>
                            <span class="nav-label">Earnings & Payouts</span>
                            @include('common.partials.soon-badge', ['feature' => 'payouts'])
                            <span class="sidebar-tooltip">Earnings & Payouts</span>
                        </a>
                        <a href="{{ route('user.monetization.earnings') }}"
                           class="sidebar-link {{ request()->routeIs('user.monetization.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-gem"></i></div>
                            <span class="nav-label">Monetization</span>
                            @include('common.partials.soon-badge', ['feature' => 'monetization'])
                            <span class="sidebar-tooltip">Monetization</span>
                        </a>
                    </div>
                </div>
                @endif

                {{-- ========== GROWTH & MARKETING (collapsible) ========== --}}
                @if($__can['links_view'] || $__can['stats_view'] || $__can['referrals_view'])
                @php $grpMarketingActive = request()->routeIs('user.social-proofs.*') || request()->routeIs('user.pixels.*') || request()->routeIs('user.referrals.*'); @endphp
                <div x-data="{ open: {{ $grpMarketingActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Growth &amp; Marketing</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['links_view'])
                        <a href="{{ route('user.social-proofs.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.social-proofs.*') ? 'active' : '' }}"
                           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bell"></i></div>
                            <span class="nav-label">Buzz</span>
                            @include('common.partials.soon-badge', ['feature' => 'social_proofs'])
                            <span class="sidebar-tooltip">Buzz</span>
                        </a>
                        @endif
                        @if($__can['stats_view'])
                        <a href="{{ route('user.pixels.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"
                           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div>
                            <span class="nav-label">Pixel</span>
                            @include('common.partials.soon-badge', ['feature' => 'pixels'])
                            <span class="sidebar-tooltip">Pixel</span>
                        </a>
                        @endif
                        @if($__can['referrals_view'])
                        <a href="{{ route('user.referrals.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.referrals.*') ? 'active' : '' }}"
                           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-gift"></i></div>
                            <span class="nav-label">Referrals</span>
                            <span class="sidebar-tooltip">Referrals</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ========== AI (collapsible) ========== --}}
                @php
                    $grpAiActive = request()->routeIs('user.ai.*') || request()->routeIs('user.minds.*') || request()->routeIs('user.ai-personas.*') || request()->routeIs('user.ai-companions.*');
                    $__aiEngineOff = !\App\Services\AI\AiEngineSettings::isEnabled();
                @endphp
                <div x-data="{ open: {{ $grpAiActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span class="inline-flex items-center gap-1.5">
                            AI
                            @if($__aiEngineOff)
                                <span class="rounded-full bg-white/10 px-1.5 py-0.5 text-[9px] font-semibold normal-case tracking-normal text-white/50"
                                      title="An administrator has turned the AI engine off">Off</span>
                            @endif
                        </span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.ai.mind.show') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.mind.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-brain"></i></div>
                            <span class="nav-label">AI Note Summarizer</span>
                            <span class="sidebar-tooltip">AI Note Summarizer</span>
                        </a>
                        @if($__can['settings_view'])
                        <a href="{{ route('user.minds.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.minds.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-network-wired"></i></div>
                            <span class="nav-label">AI Minds</span>
                            <span class="sidebar-tooltip">AI Minds</span>
                        </a>
                        @endif
                        <a href="{{ route('user.ai.persona.show') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.persona.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-user-pen"></i></div>
                            <span class="nav-label">AI Persona Generator</span>
                            <span class="sidebar-tooltip">AI Persona Generator</span>
                        </a>
                        <a href="{{ route('user.ai-personas.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai-personas.*') ? 'active' : '' }}"
                           style="--nav-tint:#f472b6; --nav-tint-soft:rgba(244,114,182,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-user-astronaut"></i></div>
                            <span class="nav-label">AI Agents</span>
                            <span class="sidebar-tooltip">AI Agents</span>
                        </a>
                        <a href="{{ route('user.ai.companion.show') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.companion.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-comments"></i></div>
                            <span class="nav-label">AI Chat</span>
                            <span class="sidebar-tooltip">AI Chat</span>
                        </a>
                        <a href="{{ route('user.ai-companions.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai-companions.*') ? 'active' : '' }}"
                           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-robot"></i></div>
                            <span class="nav-label">Chat Widgets</span>
                            <span class="sidebar-tooltip">Chat Widgets</span>
                        </a>
                        <a href="{{ route('user.brand-kits.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.brand-kits.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-palette"></i></div>
                            <span class="nav-label">AI Brand Kit</span>
                            <span class="sidebar-tooltip">AI Brand Kit</span>
                        </a>
                        <a href="{{ route('user.brand-studio.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.brand-studio.*') ? 'active' : '' }}"
                           style="--nav-tint:#2fb4ff; --nav-tint-soft:rgba(47,180,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-wand-magic-sparkles"></i></div>
                            <span class="nav-label">AI Brand Studio</span>
                            <span class="sidebar-tooltip">AI Brand Studio</span>
                        </a>
                        <a href="{{ route('user.ai.coach.show') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.coach.*') ? 'active' : '' }}"
                           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
                            <span class="nav-label">AI Link Optimizer</span>
                            <span class="sidebar-tooltip">AI Link Optimizer</span>
                        </a>
                        @if(auth()->check() && \App\Services\AI\AiEngineSettings::askCoachAllowedFor(auth()->user()))
                        <a href="{{ route('user.ai.ask-coach.show') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.ask-coach.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-comment-dots"></i></div>
                            <span class="nav-label">AI Coach</span>
                            <span class="sidebar-tooltip">AI Coach</span>
                        </a>
                        @endif
                        @if(auth()->check() && \App\Services\AI\AiEngineSettings::isEnabled() && \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'marketing_strategist'))
                        <a href="{{ route('user.ai.marketing-strategist.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.marketing-strategist.*') ? 'active' : '' }}"
                           style="--nav-tint:#34d399; --nav-tint-soft:rgba(52,211,153,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div>
                            <span class="nav-label">AI Marketing Strategist</span>
                            <span class="sidebar-tooltip">AI Marketing Strategist</span>
                        </a>
                        @endif
                        @if(auth()->check() && \App\Services\AI\AiEngineSettings::isEnabled() && (\App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_billing') || \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_contacts') || \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_general')))
                        <a href="{{ route('user.ai.staff.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.ai.staff.*') ? 'active' : '' }}"
                           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-people-group"></i></div>
                            <span class="nav-label">AI Staff</span>
                            <span class="sidebar-tooltip">AI Staff</span>
                        </a>
                        @endif
                    </div>
                </div>

                {{-- ========== WORKSPACE & TOOLS (collapsible) ========== --}}
                @if($__can['tasks_view'] || $__can['vault_view'] || $__can['settings_view'])
                @php $grpWorkspaceActive = request()->routeIs('user.tasks.*') || request()->routeIs('user.vault.*') || request()->routeIs('user.events.*') || request()->routeIs('user.calendar.*'); @endphp
                <div x-data="{ open: {{ $grpWorkspaceActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Workspace &amp; Tools</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        @if($__can['tasks_view'])
                        <a href="{{ route('user.tasks.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.tasks.*') ? 'active' : '' }}"
                           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-list-check"></i></div>
                            <span class="nav-label">Tasks</span>
                            <span class="sidebar-tooltip">Task Boards</span>
                        </a>
                        <a href="{{ route('user.delivery-projects.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.delivery-projects.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-diagram-project"></i></div>
                            <span class="nav-label">Delivery Projects</span>
                            <span class="sidebar-tooltip">Delivery Projects</span>
                        </a>
                        @endif
                        @if($__can['vault_view'])
                        <a href="{{ route('user.vault.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.vault.*') ? 'active' : '' }}"
                           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-vault"></i></div>
                            <span class="nav-label">Vault</span>
                            <span class="sidebar-tooltip">Workspace Vault</span>
                        </a>
                        @endif
                        @if($__can['settings_view'])
                        <a href="{{ route('user.events.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.events.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-calendar-day"></i></div>
                            <span class="nav-label">Events</span>
                            <span class="sidebar-tooltip">Events calendar</span>
                        </a>
                        <a href="{{ route('user.calendar.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.calendar.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-calendar-alt"></i></div>
                            <span class="nav-label">Calendar Sync</span>
                            <span class="sidebar-tooltip">Calendar Sync</span>
                        </a>
                        @endif
                    </div>
                </div>
                @endif

                {{-- ========== BILLING & ACCOUNTING (collapsible) ========== --}}
                @if($__can['tasks_view'])
                @php $grpBillingActive = request()->routeIs('user.client-invoices.*') || request()->routeIs('user.billing.*'); @endphp
                <div x-data="{ open: {{ $grpBillingActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Billing &amp; Accounting</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        <a href="{{ route('user.client-invoices.dashboard') }}"
                           class="sidebar-link {{ request()->routeIs('user.client-invoices.*') ? 'active' : '' }}"
                           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-file-invoice-dollar"></i></div>
                            <span class="nav-label">Invoices</span>
                            <span class="sidebar-tooltip">Invoices</span>
                        </a>
                        <a href="{{ route('user.billing.recurring.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.billing.recurring.*') ? 'active' : '' }}"
                           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-repeat"></i></div>
                            <span class="nav-label">Recurring</span>
                            <span class="sidebar-tooltip">Recurring Invoices</span>
                        </a>
                        <a href="{{ route('user.billing.expenses.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.billing.expenses.*') ? 'active' : '' }}"
                           style="--nav-tint:#ef4444; --nav-tint-soft:rgba(239,68,68,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-receipt"></i></div>
                            <span class="nav-label">Expenses</span>
                            <span class="sidebar-tooltip">Expenses</span>
                        </a>
                        <a href="{{ route('user.billing.catalog.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.billing.catalog.*') ? 'active' : '' }}"
                           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-boxes-stacked"></i></div>
                            <span class="nav-label">Catalog</span>
                            <span class="sidebar-tooltip">Item Catalog</span>
                        </a>
                        <a href="{{ route('user.billing.tax-rules.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.billing.tax-rules.*') ? 'active' : '' }}"
                           style="--nav-tint:#64748b; --nav-tint-soft:rgba(100,116,139,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-percent"></i></div>
                            <span class="nav-label">Tax Rules</span>
                            <span class="sidebar-tooltip">Tax Rules</span>
                        </a>
                        <a href="{{ route('user.billing.ledger.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.billing.ledger.*') ? 'active' : '' }}"
                           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
                            <span class="nav-label">Ledger</span>
                            <span class="sidebar-tooltip">Ledger / P&amp;L</span>
                        </a>
                    </div>
                </div>
                @endif

                {{-- ========== ACCOUNT (collapsible) ========== --}}
                @if($__can['settings_view'])
                @php $grpAccountActive = \App\Modules\User\Support\SettingsTabs::activeKey() !== null; @endphp
                <div x-data="{ open: {{ $grpAccountActive ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                            class="sidebar-group-toggle section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                        <span>Account</span>
                        <i class="fas fa-chevron-down grp-chevron"></i>
                    </button>
                    <div x-show="open || sidebarMode === 'icons'" x-cloak>
                        {{-- Consolidated Settings hub (Task #3220) — one entry
                             replaces the old Profile / Verification / Badge /
                             Devices & sessions / API keys links, all now tabs. --}}
                        <a href="{{ route('user.profile.edit') }}"
                           class="sidebar-link {{ $grpAccountActive ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-sliders"></i></div>
                            <span class="nav-label">Settings</span>
                            <span class="sidebar-tooltip">Settings</span>
                        </a>
                        <a href="{{ route('user.emails.index') }}"
                           class="sidebar-link {{ request()->routeIs('user.emails.*') ? 'active' : '' }}"
                           style="--nav-tint:#90acff; --nav-tint-soft:rgba(144,172,255,0.12);">
                            <div class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></div>
                            <span class="nav-label">Email history</span>
                            <span class="sidebar-tooltip">Email history</span>
                        </a>
                    </div>
                </div>
                @endif

            </div>{{-- /.sidebar-nav-scroll --}}
            </nav>

            <div class="mx-3 mb-3" x-show="sidebarMode === 'full'" x-cloak x-transition.opacity>
                <div class="upgrade-card">
                    <div class="relative z-10 upgrade-inner">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-6 h-6 rounded-lg bg-blue-500 flex items-center justify-center">
                                <i class="fas fa-gem text-white text-[8px]"></i>
                            </div>
                            <span class="text-xs font-bold" style="color: var(--text-primary);">{{ auth()->user()->plan->name ?? 'Free' }} Plan</span>
                        </div>
                        <p class="text-[10px] mb-3 leading-relaxed" style="color: var(--text-dimmed);">Unlock analytics, custom domains & more.</p>
                        <a href="#" class="block text-center text-[10px] font-bold uppercase tracking-wider py-2 rounded-lg text-white transition-all bg-blue-600 hover:shadow-lg hover:shadow">
                            Upgrade
                        </a>
                    </div>
                </div>
            </div>

            @if(!session('impersonate_user_id') && auth()->user()->hasActiveAdminAccount())
            <div class="px-3 pb-2" x-show="sidebarMode !== 'icons'" x-cloak>
                <form action="{{ route('user.switch-to-admin') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition-all"
                            style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.25); color: var(--accent-light);"
                            title="Switch to the admin dashboard">
                        <i class="fas fa-user-shield" style="font-size: 11px;"></i>
                        <span>Switch to admin</span>
                    </button>
                </form>
            </div>
            <div class="px-3 pb-1" x-show="sidebarMode === 'icons'" x-cloak>
                <form action="{{ route('user.switch-to-admin') }}" method="POST" class="flex justify-center">
                    @csrf
                    <button type="submit" class="logout-btn" title="Switch to admin dashboard" style="color: var(--accent-light);">
                        <i class="fas fa-user-shield text-xs"></i>
                    </button>
                </form>
            </div>
            @endif

            {{-- ========== ACCOUNT (collapsible) — coins + assigned badges ==========
                 Lives next to the user profile so the wallet balance and admin-
                 assigned account badges are reachable from anywhere in the app.
                 Hidden in the icons-collapsed mode (like the upgrade card /
                 profile block) since the content is text, not icon links. --}}
            @php
                // Task #3848 — the coin balance moved to the header icon+badge,
                // so this section is now badges-only (no more duplicate coin
                // surface here).
                $__acctBadges  = auth()->user()->accountBadges;
                $__showAccount = $__acctBadges->isNotEmpty();
            @endphp
            @if($__showAccount)
            <div class="px-3 pb-2" x-show="sidebarMode !== 'icons'" x-cloak x-data="{
                    open: true,
                    init() {
                        try {
                            const saved = localStorage.getItem('1inme_sidebar_account_open');
                            if (saved !== null) this.open = saved === '1';
                        } catch (e) {}
                        this.$watch('open', (val) => {
                            try { localStorage.setItem('1inme_sidebar_account_open', val ? '1' : '0'); } catch (e) {}
                        });
                    }
                }">
                <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                        class="sidebar-group-toggle pt-2 pb-1.5 px-1 text-[10px] font-bold uppercase tracking-[0.15em]">
                    <span>Account</span>
                    <i class="fas fa-chevron-down grp-chevron"></i>
                </button>
                <div x-show="open" x-cloak class="space-y-2 pt-1">
                    @if($__acctBadges->isNotEmpty())
                    <div class="px-1">
                        <p class="text-[9px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Your badges</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($__acctBadges as $badge)
                            <span class="badge inline-flex items-center gap-1.5 text-[10px]"
                                  style="background: {{ $badge->color }}26; color: {{ $badge->color }}; border: 1px solid {{ $badge->color }}40;">
                                <i class="fas fa-certificate text-[9px]"></i>{{ $badge->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <div class="px-3 py-3" style="border-top: 1px solid var(--border-strong);">
                <div class="flex items-center" :class="sidebarMode === 'icons' ? 'justify-center' : 'gap-3'">
                    <div class="user-avatar-ring flex-shrink-0">
                        <div class="inner">
                            <img src="{{ auth()->user()->resolveAvatarUrl() }}"
                                 alt="{{ auth()->user()->name }}"
                                 class="w-full h-full rounded-[10px] object-cover"
                                 onerror="this.style.display='none';this.parentNode.textContent='{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}';">
                        </div>
                    </div>
                    <div class="flex-1 min-w-0 user-info">
                        <p class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ auth()->user()->name }}</p>
                        <p class="text-[10px] truncate" style="color: var(--text-dimmed);">{{ auth()->user()->email }}</p>
                    </div>
                    <form action="{{ route('user.logout') }}" method="POST" class="user-info">
                        @csrf
                        <button type="submit" class="logout-btn" title="Logout">
                            <i class="fas fa-sign-out-alt text-xs"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0 min-h-0 main-content-v2"
             :style="'margin-left:' + (isDesktop ? sidebarWidth : 0) + 'px'">

            <header class="flex-shrink-0 flex items-center justify-between px-4 lg:px-6 z-20 header-v2 relative dash-glass !rounded-none"
                    style="height: var(--app-header-h);">
                <div class="header-glow"></div>

                <div class="flex items-center gap-3 min-w-0">
                    <button @click="mobileMenu = !mobileMenu" class="flex lg:hidden header-icon-btn flex-shrink-0" style="width: 34px; height: 34px;">
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

                    {{-- Search now lives entirely in the ⌘K command palette
                         (common.partials.global-shortcuts), which folds in the
                         universal finder (contacts, people, links, followed,
                         workspaces) alongside nav shortcuts. This trigger just
                         opens it. --}}
                    {{-- Full-screen search overlay trigger.
                         Fires `open-full-search` which is handled by
                         user.partials.global-search-overlay (included at
                         end of this layout). Shown on ALL screen sizes since
                         the mobile drawer also has its own search button. --}}
                    <button type="button" class="header-icon-btn flex"
                            onclick="window.dispatchEvent(new CustomEvent('open-full-search'))"
                            title="Search" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>

                    <div class="hidden lg:flex items-center">
                        @include('common.partials.theme-toggle')
                    </div>

                    {{-- Task #3848 — compact coin-wallet icon + balance badge,
                         replacing the old sidebar Wallet nav item. Badge is
                         red below the wallet's low-balance threshold, green
                         at/above it; shows 0 (and reads red) when the Wallet
                         feature is disabled by the admin. Kept live via the
                         [data-wallet-badge] refresh script further down this
                         file (dispatch `wallet:refresh`/`wallet:balance` after
                         any coin-changing action). --}}
                    @php
                        $__hdrWalletEnabled = \App\Services\Billing\WalletService::isEnabled();
                        $__hdrCoins = 0;
                        $__hdrThreshold = 100;
                        if ($__hdrWalletEnabled) {
                            $__hdrWallet = app(\App\Services\Billing\WalletService::class)->walletFor(auth()->user());
                            $__hdrCoins = (int) $__hdrWallet->balance;
                            $__hdrThreshold = (int) ($__hdrWallet->low_balance_threshold ?? 100);
                        }
                        $__hdrLow = !$__hdrWalletEnabled || ($__hdrThreshold > 0 && $__hdrCoins < $__hdrThreshold);
                    @endphp
                    @php
                        $__hdrTransactions = collect();
                        if ($__hdrWalletEnabled) {
                            $__hdrTransactions = $__hdrWallet->transactions()->limit(5)->get();
                        }
                    @endphp
                    <div x-data="{ open: false }" class="relative">
                        <button type="button"
                                @click="open = !open"
                                class="header-icon-btn relative flex"
                                data-wallet-threshold="{{ $__hdrThreshold }}"
                                title="Coin balance: {{ number_format($__hdrCoins) }}{{ $__hdrWalletEnabled ? '' : ' (wallet disabled)' }}"
                                aria-label="Coin wallet balance">
                            <i class="fas fa-coins" style="font-size: 12px;"></i>
                            <span data-wallet-badge
                                  class="absolute -top-1 -right-1.5 min-w-[16px] h-[16px] px-1 inline-flex items-center justify-center rounded-full text-[9px] font-bold leading-none text-white {{ $__hdrLow ? 'bg-red-500' : 'bg-emerald-500' }}"
                                  title="{{ number_format($__hdrCoins) }} coins">{{ $__hdrCoins >= 1000 ? round($__hdrCoins / 1000, 1) . 'k' : $__hdrCoins }}</span>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak x-transition
                             class="absolute right-0 mt-2 w-72 rounded-xl py-2 z-50" style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
                            <div class="px-3.5 pb-2 flex items-center justify-between" style="border-bottom: 1px solid var(--border-subtle);">
                                <span class="text-xs font-semibold" style="color: var(--text-primary);">Coin activity</span>
                                <span class="text-xs" style="color: var(--text-muted);">{{ number_format($__hdrCoins) }} coins</span>
                            </div>
                            @if(!$__hdrWalletEnabled)
                                <p class="px-3.5 py-3 text-xs" style="color: var(--text-muted);">Wallet is currently disabled by your admin.</p>
                            @elseif($__hdrTransactions->isEmpty())
                                <p class="px-3.5 py-3 text-xs" style="color: var(--text-muted);">No transactions yet.</p>
                            @else
                                <div class="max-h-64 overflow-y-auto">
                                    @foreach($__hdrTransactions as $__hdrTx)
                                        <div class="px-3.5 py-2 flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-xs truncate" style="color: var(--text-primary);">{{ $__hdrTx->reason ?? ucfirst($__hdrTx->type) }}</p>
                                                <p class="text-[10px]" style="color: var(--text-muted);">{{ $__hdrTx->created_at->diffForHumans() }}</p>
                                            </div>
                                            <span class="text-xs font-semibold whitespace-nowrap {{ $__hdrTx->delta_coins >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                                {{ $__hdrTx->delta_coins >= 0 ? '+' : '' }}{{ number_format($__hdrTx->delta_coins) }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <div class="px-3.5 pt-2 mt-1" style="border-top: 1px solid var(--border-subtle);">
                                <a href="{{ route('user.wallet.show') }}" class="text-xs font-medium text-blue-400 hover:underline">View wallet &rarr;</a>
                            </div>
                        </div>
                    </div>

                    @php
                        $__headerUnread = \App\Modules\User\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count();
                        $__hdrNotifications = \App\Modules\User\Models\UserNotification::where('user_id', auth()->id())
                            ->orderByDesc('created_at')
                            ->limit(5)
                            ->get();
                    @endphp
                    <div x-data="{ open: false }" class="relative">
                        <button type="button"
                                @click="open = !open"
                                class="header-icon-btn relative hidden sm:flex {{ request()->routeIs('user.notifications.*') ? 'active' : '' }}"
                                title="Notifications"
                                aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            @if($__headerUnread)<span class="badge-dot"></span>@endif
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak x-transition
                             class="absolute right-0 mt-2 w-80 rounded-xl py-2 z-50" style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
                            <div class="px-3.5 pb-2 flex items-center justify-between" style="border-bottom: 1px solid var(--border-subtle);">
                                <span class="text-xs font-semibold" style="color: var(--text-primary);">Notifications</span>
                                @if($__headerUnread)<span class="text-xs" style="color: var(--text-muted);">{{ $__headerUnread }} unread</span>@endif
                            </div>
                            @if($__hdrNotifications->isEmpty())
                                <p class="px-3.5 py-3 text-xs" style="color: var(--text-muted);">No notifications yet.</p>
                            @else
                                <div class="max-h-80 overflow-y-auto">
                                    @foreach($__hdrNotifications as $__hdrN)
                                        @php $__hdrD = is_array($__hdrN->data) ? $__hdrN->data : []; @endphp
                                        <a href="{{ route('user.notifications.open', $__hdrN->id) }}"
                                           class="px-3.5 py-2 flex items-start gap-2.5 transition-colors hover:bg-blue-500/5 {{ $__hdrN->read_at ? '' : 'bg-blue-50/30' }}">
                                            @include('user.notifications._icon', ['n' => $__hdrN, 'd' => $__hdrD, 'size' => 'w-8 h-8'])
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs {{ $__hdrN->read_at ? '' : 'font-semibold' }}" style="color: var(--text-primary);">{{ \Illuminate\Support\Str::limit($__hdrN->previewText(), 90) }}</p>
                                                <p class="text-[10px] mt-0.5" style="color: var(--text-muted);">{{ $__hdrN->created_at?->diffForHumans() ?? 'recently' }}</p>
                                            </div>
                                            @if(!$__hdrN->read_at)
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-1.5 flex-shrink-0"></span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            <div class="px-3.5 pt-2 mt-1" style="border-top: 1px solid var(--border-subtle);">
                                <a href="{{ route('user.notifications.index') }}" class="text-xs font-medium text-blue-400 hover:underline">View all notifications &rarr;</a>
                            </div>
                        </div>
                    </div>

                    @if($__can['links_create'])
                        @include('user.partials.quick-shorten')
                    @endif

                    <a href="{{ route('user.links.create') }}" class="btn-primary btn-primary-gradient hidden sm:inline-flex items-center gap-1.5 text-xs px-3.5 py-2 whitespace-nowrap">
                        <i class="fas fa-plus" style="font-size: 9px;"></i>
                        <span>New Link</span>
                    </a>

                    <div x-data="{ open: false }" class="relative lg:hidden">
                        <button @click="open = !open" class="header-icon-btn flex">
                            <i class="fas fa-ellipsis-v text-xs"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak x-transition
                             class="absolute right-0 mt-2 w-48 rounded-xl py-1.5 z-50" style="background: var(--bg-sidebar); border: 1px solid var(--border-subtle); box-shadow: var(--card-shadow);">
                            <div class="px-3 py-2" style="border-bottom: 1px solid var(--border-subtle);">
                                @include('common.partials.theme-toggle')
                            </div>
                            <a href="{{ route('user.profile.edit') }}" class="flex items-center gap-2.5 px-3 py-2 text-xs hover:opacity-80 transition-opacity" style="color: var(--text-muted);">
                                <i class="fas fa-sliders" style="width: 14px; text-align: center;"></i> Settings
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
                <div class="w-full h-full flex flex-col dash-glass">
                    <div class="h-[64px] flex items-center justify-between px-5" style="border-bottom: 1px solid var(--border-subtle);">
                        <div class="flex items-center gap-2.5">
                            @include('common.partials.brand-logo', ['height' => 'h-7'])
                        </div>
                        <button @click="mobileMenu = false" class="p-1.5 rounded-lg" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                    </div>
                    @auth
                        @include('user.partials.workspace-switcher')
                    @endauth
                    <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto overscroll-contain" style="-webkit-overflow-scrolling: touch;">
                        {{-- Search button (opens full-screen overlay) --}}
                        <button type="button"
                                @click="mobileMenu = false; $nextTick(() => window.dispatchEvent(new CustomEvent('open-full-search')))"
                                class="sidebar-link w-full text-left mb-1"
                                style="color: var(--text-muted);">
                            <div class="nav-icon-wrap"><i class="fas fa-search"></i></div>
                            <span>Search</span>
                        </button>
                        {{-- ========== TOP LEVEL — mirrors desktop most-used destinations ========== --}}
                        <a href="{{ route('user.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-house"></i></div> <span>Dashboard</span></a>
                        @if($__can['links_view'])
                        <a href="{{ route('user.links.index') }}" class="sidebar-link {{ request()->routeIs('user.links.index') || request()->routeIs('user.links.show') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-link"></i></div> <span>All Links</span></a>
                        @endif
                        @if($__can['links_create'])
                        <a href="{{ route('user.links.create') }}" class="sidebar-link {{ request()->routeIs('user.links.create') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-plus-circle"></i></div> <span>Create Link</span></a>
                        {{-- Clipboard quick-shorten — mirrors the desktop header shortcut button (Task #6285) --}}
                        <button type="button"
                                @click="mobileMenu = false; $nextTick(() => window.dispatchEvent(new CustomEvent('open-quick-shorten')))"
                                class="sidebar-link w-full text-left" style="color: var(--text-muted);">
                            <div class="nav-icon-wrap"><i class="fas fa-bolt"></i></div> <span>Quick Shorten</span>
                        </button>
                        @endif
                        @if($__can['links_view'])
                        <a href="{{ route('user.calendars.mine') }}" class="sidebar-link {{ request()->routeIs('user.calendars.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-calendar-days"></i></div> <span>My Calendar</span></a>
                        @endif
                        @if($__can['inbox_view'])
                        <a href="{{ route('user.inbox.unified.index') }}" class="sidebar-link {{ request()->routeIs('user.inbox.unified.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-inbox"></i></div> <span>Inbox
                            @php $__mTopInbox = (new \App\Modules\User\Services\InboxAggregator(auth()->id()))->unreadCount(); @endphp
                            @if($__mTopInbox)<span class="ml-1 inline-block px-1.5 rounded-full text-[10px] bg-blue-500 text-white">{{ $__mTopInbox > 99 ? '99+' : $__mTopInbox }}</span>@endif
                        </span></a>
                        @endif
                        <a href="{{ route('user.notifications.index') }}" class="sidebar-link {{ request()->routeIs('user.notifications.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bell"></i></div> <span>Notifications
                            @php $__mUnread = \App\Modules\User\Models\UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count(); @endphp
                            @if($__mUnread)<span class="ml-1 inline-block px-1.5 rounded-full text-[10px] bg-rose-500 text-white">{{ $__mUnread }}</span>@endif
                        </span></a>
                        @if($__can['posts_view'])
                        <a href="{{ route('user.stats.index') }}" class="sidebar-link {{ request()->routeIs('user.stats.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div> <span>Stats</span></a>
                        @endif
                        @if($__can['stats_view'])
                        <a href="{{ route('user.visitors.index') }}" class="sidebar-link {{ request()->routeIs('user.visitors.index') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-users"></i></div> <span>Visitors</span></a>
                        @endif
                        {{-- Task #3848 — Wallet nav item moved to the header icon+badge (mobile drawer's header mirrors the desktop one). --}}

                        {{-- ========== LINKS & PAGES (collapsible) ========== --}}
                        @if($__can['links_view'] || $__can['inbox_view'] || $__can['files_view'])
                        @php $mGrpLinksActive = request()->routeIs('user.qr-codes.*') || request()->routeIs('user.qrcode*') || request()->routeIs('user.forms.*') || request()->routeIs('user.backlinks.*') || request()->routeIs('user.splash-pages.*') || request()->routeIs('user.resume.*') || request()->routeIs('user.projects.*') || request()->routeIs('user.files.*') || request()->routeIs('user.cloud-files.*') || request()->routeIs('user.cloud-oauth.*'); @endphp
                        <div x-data="{ open: {{ $mGrpLinksActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Links &amp; Pages</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                @if($__can['links_view'])
                                <a href="{{ route('user.qr-codes.index') }}" class="sidebar-link {{ request()->routeIs('user.qr-codes.*') || request()->routeIs('user.qrcode*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-qrcode"></i></div> <span>QR Codes</span></a>
                                @endif
                                @if($__can['inbox_view'])
                                <a href="{{ route('user.forms.index') }}" class="sidebar-link {{ request()->routeIs('user.forms.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-clipboard-list"></i></div> <span>Forms</span></a>
                                @endif
                                @if($__can['links_view'])
                                <a href="{{ route('user.backlinks.index') }}" class="sidebar-link {{ request()->routeIs('user.backlinks.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div> <span>Backlinks</span></a>
                                <a href="{{ route('user.splash-pages.index') }}" class="sidebar-link {{ request()->routeIs('user.splash-pages.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-rocket"></i></div> <span>Intros</span></a>
                                <a href="{{ route('user.resume.editor') }}" class="sidebar-link {{ request()->routeIs('user.resume.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-file-lines"></i></div> <span>AI Resume / Portfolio</span></a>
                                <div x-data="{ mFoldersOpen: {{ request()->routeIs('user.projects.*') || request()->filled('project_id') ? 'true' : 'false' }} }">
                                    <div class="flex items-center">
                                        <a href="{{ route('user.projects.index') }}" class="sidebar-link flex-1 {{ request()->routeIs('user.projects.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-folder"></i></div> <span>Folders</span></a>
                                        @php $__mNavFolders = workspace_owner()->projects()->orderBy('name')->limit(10)->get(['id', 'name', 'color']); @endphp
                                        @if($__mNavFolders->isNotEmpty())
                                        <button type="button" @click="mFoldersOpen = !mFoldersOpen" class="p-1.5 mr-1 rounded-lg text-white/30 hover:text-white/70 hover:bg-white/10" :aria-expanded="mFoldersOpen ? 'true' : 'false'" aria-label="Toggle folders list">
                                            <i class="fas fa-chevron-down text-[10px] transition-transform" :class="mFoldersOpen ? '' : '-rotate-90'"></i>
                                        </button>
                                        @endif
                                    </div>
                                    <div x-show="mFoldersOpen" x-collapse class="pl-9 pr-2 pb-1 space-y-0.5">
                                        @foreach($__mNavFolders as $__mnf)
                                        <a href="{{ route('user.links.index', ['project_id' => $__mnf->id]) }}" class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px] {{ request('project_id') == $__mnf->id ? 'text-white bg-white/10' : 'text-white/50 hover:text-white hover:bg-white/5' }}">
                                            <i class="fas fa-folder text-xs" style="color: {{ $__mnf->color ?: '#3b82f6' }}"></i>
                                            <span class="truncate">{{ $__mnf->name }}</span>
                                        </a>
                                        @endforeach
                                        <a href="{{ route('user.projects.create') }}" class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-[13px] text-white/40 hover:text-white hover:bg-white/5">
                                            <i class="fas fa-plus text-xs"></i>
                                            <span>New folder</span>
                                        </a>
                                    </div>
                                </div>
                                @endif
                                @if($__can['files_view'] || $__can['links_view'])
                                @php
                                    $__filesLastTab = session('files_last_tab', 'vault');
                                    if ($__filesLastTab === 'cloud' && $__can['files_view']) {
                                        $filesHref = route('user.cloud-files.index');
                                    } elseif ($__can['links_view']) {
                                        $filesHref = route('user.files.index');
                                    } else {
                                        $filesHref = route('user.cloud-files.index');
                                    }
                                @endphp
                                <a href="{{ $filesHref }}" class="sidebar-link {{ request()->routeIs('user.files.*') || request()->routeIs('user.cloud-files.*') || request()->routeIs('user.cloud-oauth.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-folder-open"></i></div> <span>Files</span></a>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- ========== AUDIENCE & COMMUNITY (collapsible) ========== --}}
                        @if($__can['inbox_view'] || $__can['followers_view'] || $__can['posts_view'] || $__can['settings_view'])
                        @php $mGrpAudienceActive = request()->routeIs('user.subscribers.*') || request()->routeIs('user.followers.*') || request()->routeIs('user.following.*') || request()->routeIs('user.feed.*') || request()->routeIs('user.creator-profile.*') || request()->routeIs('user.posts.*') || request()->routeIs('user.contacts.*') || request()->routeIs('user.dialer.*'); @endphp
                        <div x-data="{ open: {{ $mGrpAudienceActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Audience &amp; Community</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                @if($__can['inbox_view'])
                                <a href="{{ route('user.subscribers.index') }}" class="sidebar-link {{ request()->routeIs('user.subscribers.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-users"></i></div> <span>Subscribers</span></a>
                                @endif
                                @if($__can['followers_view'])
                                <a href="{{ route('user.followers.index') }}" class="sidebar-link {{ request()->routeIs('user.followers.*') || request()->routeIs('user.following.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-group"></i></div> <span>Followers</span></a>
                                @endif
                                @if($__can['posts_view'])
                                <a href="{{ route('feed.index') }}" class="sidebar-link {{ request()->routeIs('user.feed.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-stream"></i></div> <span>Feed</span></a>
                                <a href="{{ route('user.creator-profile.edit') }}" class="sidebar-link {{ request()->routeIs('user.creator-profile.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-id-badge"></i></div> <span>Creator Profile</span></a>
                                <a href="{{ route('user.posts.index') }}" class="sidebar-link {{ request()->routeIs('user.posts.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-pen-to-square"></i></div> <span>My Posts</span></a>
                                @endif
                                @if($__can['settings_view'])
                                <a href="{{ route('user.leads.index') }}" class="sidebar-link {{ request()->routeIs('user.leads.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-plus"></i></div> <span>Leads</span>@if(($pendingLeadsCount ?? 0) > 0)<span class="ml-auto inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold leading-none text-white" style="background:#ef4444;" title="{{ $pendingLeadsCount }} pending lead(s)">{{ $pendingLeadsCount > 99 ? '99+' : $pendingLeadsCount }}</span>@endif</a>
                                <a href="{{ route('user.contacts.index') }}" class="sidebar-link {{ request()->routeIs('user.contacts.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-address-book"></i></div> <span>Contacts</span>@if(($contactsOverdueFollowUps ?? 0) > 0)<span class="ml-auto inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold leading-none text-white" style="background:#ef4444;" title="{{ $contactsOverdueFollowUps }} overdue follow-up(s)">{{ $contactsOverdueFollowUps > 99 ? '99+' : $contactsOverdueFollowUps }}</span>@endif</a>
                                <a href="{{ route('user.dialer.index') }}" class="sidebar-link {{ request()->routeIs('user.dialer.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-phone"></i></div> <span>Dialer</span>@include('common.partials.soon-badge', ['feature' => 'dialer'])</a>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- ========== MONETIZATION (collapsible) ========== --}}
                        @if($__can['posts_view'])
                        @php $mGrpMonetActive = request()->routeIs('user.payouts.*') || request()->routeIs('user.adult-content.*') || request()->routeIs('user.monetization.*'); @endphp
                        <div x-data="{ open: {{ $mGrpMonetActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Monetization</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                <a href="{{ route('user.payouts.show') }}" class="sidebar-link {{ request()->routeIs('user.payouts.*') || request()->routeIs('user.adult-content.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-sack-dollar"></i></div> <span>Earnings &amp; Payouts</span>@include('common.partials.soon-badge', ['feature' => 'payouts'])</a>
                                <a href="{{ route('user.monetization.earnings') }}" class="sidebar-link {{ request()->routeIs('user.monetization.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-gem"></i></div> <span>Monetization</span>@include('common.partials.soon-badge', ['feature' => 'monetization'])</a>
                            </div>
                        </div>
                        @endif

                        {{-- ========== GROWTH & MARKETING (collapsible) ========== --}}
                        @if($__can['links_view'] || $__can['stats_view'] || $__can['referrals_view'])
                        @php $mGrpMarketingActive = request()->routeIs('user.social-proofs.*') || request()->routeIs('user.pixels.*') || request()->routeIs('user.referrals.*'); @endphp
                        <div x-data="{ open: {{ $mGrpMarketingActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Growth &amp; Marketing</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                @if($__can['links_view'])
                                <a href="{{ route('user.social-proofs.index') }}" class="sidebar-link {{ request()->routeIs('user.social-proofs.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bell"></i></div> <span>Buzz</span>@include('common.partials.soon-badge', ['feature' => 'social_proofs'])</a>
                                @endif
                                @if($__can['stats_view'])
                                <a href="{{ route('user.pixels.index') }}" class="sidebar-link {{ request()->routeIs('user.pixels.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div> <span>Pixel</span>@include('common.partials.soon-badge', ['feature' => 'pixels'])</a>
                                @endif
                                @if($__can['referrals_view'])
                                <a href="{{ route('user.referrals.index') }}" class="sidebar-link {{ request()->routeIs('user.referrals.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-gift"></i></div> <span>Referrals</span></a>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- ========== AI (collapsible) ========== --}}
                        @php
                            $mGrpAiActive = request()->routeIs('user.ai.*') || request()->routeIs('user.minds.*') || request()->routeIs('user.ai-personas.*') || request()->routeIs('user.ai-companions.*');
                            $__aiEngineOff = !\App\Services\AI\AiEngineSettings::isEnabled();
                        @endphp
                        <div x-data="{ open: {{ $mGrpAiActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span class="inline-flex items-center gap-1.5">
                                    AI
                                    @if($__aiEngineOff)
                                        <span class="rounded-full bg-white/10 px-1.5 py-0.5 text-[9px] font-semibold normal-case tracking-normal text-white/50"
                                              title="An administrator has turned the AI engine off">Off</span>
                                    @endif
                                </span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                <a href="{{ route('user.ai.mind.show') }}" class="sidebar-link {{ request()->routeIs('user.ai.mind.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-brain"></i></div> <span>AI Note Summarizer</span></a>
                                @if($__can['settings_view'])
                                <a href="{{ route('user.minds.index') }}" class="sidebar-link {{ request()->routeIs('user.minds.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-network-wired"></i></div> <span>AI Minds</span></a>
                                @endif
                                <a href="{{ route('user.ai.persona.show') }}" class="sidebar-link {{ request()->routeIs('user.ai.persona.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-pen"></i></div> <span>AI Persona Generator</span></a>
                                <a href="{{ route('user.ai-personas.index') }}" class="sidebar-link {{ request()->routeIs('user.ai-personas.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-astronaut"></i></div> <span>AI Agents</span></a>
                                <a href="{{ route('user.ai.companion.show') }}" class="sidebar-link {{ request()->routeIs('user.ai.companion.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-comments"></i></div> <span>AI Chat</span></a>
                                <a href="{{ route('user.ai-companions.index') }}" class="sidebar-link {{ request()->routeIs('user.ai-companions.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-robot"></i></div> <span>Chat Widgets</span></a>
                                <a href="{{ route('user.brand-kits.index') }}" class="sidebar-link {{ request()->routeIs('user.brand-kits.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-palette"></i></div> <span>AI Brand Kit</span></a>
                                <a href="{{ route('user.brand-studio.index') }}" class="sidebar-link {{ request()->routeIs('user.brand-studio.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-wand-magic-sparkles"></i></div> <span>AI Brand Studio</span></a>
                                <a href="{{ route('user.ai.coach.show') }}" class="sidebar-link {{ request()->routeIs('user.ai.coach.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div> <span>AI Link Optimizer</span></a>
                                @if(auth()->check() && \App\Services\AI\AiEngineSettings::askCoachAllowedFor(auth()->user()))
                                <a href="{{ route('user.ai.ask-coach.show') }}" class="sidebar-link {{ request()->routeIs('user.ai.ask-coach.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-comment-dots"></i></div> <span>AI Coach</span></a>
                                @endif
                                @if(auth()->check() && \App\Services\AI\AiEngineSettings::isEnabled() && \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'marketing_strategist'))
                                <a href="{{ route('user.ai.marketing-strategist.index') }}" class="sidebar-link {{ request()->routeIs('user.ai.marketing-strategist.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullseye"></i></div> <span>AI Marketing Strategist</span></a>
                                @endif
                                @if(auth()->check() && \App\Services\AI\AiEngineSettings::isEnabled() && (\App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_billing') || \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_contacts') || \App\Services\AI\AiPlanAccess::featureAllowed(auth()->user(), 'ai_staff_general')))
                                <a href="{{ route('user.ai.staff.index') }}" class="sidebar-link {{ request()->routeIs('user.ai.staff.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-people-group"></i></div> <span>AI Staff</span></a>
                                @endif
                            </div>
                        </div>

                        {{-- ========== WORKSPACE & TOOLS (collapsible) ========== --}}
                        @if($__can['tasks_view'] || $__can['vault_view'] || $__can['settings_view'])
                        @php $mGrpWorkspaceActive = request()->routeIs('user.tasks.*') || request()->routeIs('user.vault.*') || request()->routeIs('user.events.*') || request()->routeIs('user.calendar.*'); @endphp
                        <div x-data="{ open: {{ $mGrpWorkspaceActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Workspace &amp; Tools</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                @if($__can['tasks_view'])
                                <a href="{{ route('user.tasks.index') }}" class="sidebar-link {{ request()->routeIs('user.tasks.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-list-check"></i></div> <span>Tasks</span></a>
                                <a href="{{ route('user.delivery-projects.index') }}" class="sidebar-link {{ request()->routeIs('user.delivery-projects.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-diagram-project"></i></div> <span>Delivery Projects</span></a>
                                @endif
                                @if($__can['vault_view'])
                                <a href="{{ route('user.vault.index') }}" class="sidebar-link {{ request()->routeIs('user.vault.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-vault"></i></div> <span>Vault</span></a>
                                @endif
                                @if($__can['settings_view'])
                                <a href="{{ route('user.events.index') }}" class="sidebar-link {{ request()->routeIs('user.events.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-calendar-day"></i></div> <span>Events</span></a>
                                <a href="{{ route('user.calendar.index') }}" class="sidebar-link {{ request()->routeIs('user.calendar.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-calendar-alt"></i></div> <span>Calendar Sync</span></a>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- ========== BILLING & ACCOUNTING (collapsible) ========== --}}
                        @if($__can['tasks_view'])
                        <div x-data="{ open: {{ (request()->routeIs('user.client-invoices.*') || request()->routeIs('user.billing.*')) ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Billing &amp; Accounting</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                <a href="{{ route('user.client-invoices.dashboard') }}" class="sidebar-link {{ request()->routeIs('user.client-invoices.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-file-invoice-dollar"></i></div> <span>Invoices</span></a>
                                <a href="{{ route('user.billing.recurring.index') }}" class="sidebar-link {{ request()->routeIs('user.billing.recurring.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-repeat"></i></div> <span>Recurring</span></a>
                                <a href="{{ route('user.billing.expenses.index') }}" class="sidebar-link {{ request()->routeIs('user.billing.expenses.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-receipt"></i></div> <span>Expenses</span></a>
                                <a href="{{ route('user.billing.catalog.index') }}" class="sidebar-link {{ request()->routeIs('user.billing.catalog.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-boxes-stacked"></i></div> <span>Catalog</span></a>
                                <a href="{{ route('user.billing.tax-rules.index') }}" class="sidebar-link {{ request()->routeIs('user.billing.tax-rules.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-percent"></i></div> <span>Tax Rules</span></a>
                                <a href="{{ route('user.billing.ledger.index') }}" class="sidebar-link {{ request()->routeIs('user.billing.ledger.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div> <span>Ledger</span></a>
                            </div>
                        </div>
                        @endif

                        {{-- ========== ACCOUNT (collapsible) ========== --}}
                        @if($__can['settings_view'])
                        @php $mGrpAccountActive = \App\Modules\User\Support\SettingsTabs::activeKey() !== null || request()->routeIs('user.identifiers.*'); @endphp
                        <div x-data="{ open: {{ $mGrpAccountActive ? 'true' : 'false' }} }">
                            <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                    class="sidebar-group-toggle pt-4 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]">
                                <span>Account</span>
                                <i class="fas fa-chevron-down grp-chevron"></i>
                            </button>
                            <div x-show="open" x-cloak class="space-y-0.5">
                                {{-- Consolidated Settings hub (Task #3220) — one entry
                                     replaces Profile / Verification / Devices & sessions
                                     / API keys, all now tabs inside the hub. --}}
                                <a href="{{ route('user.profile.edit') }}" class="sidebar-link {{ \App\Modules\User\Support\SettingsTabs::activeKey() !== null ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-sliders"></i></div> <span>Settings</span></a>
                                <a href="{{ route('user.identifiers.index') }}" class="sidebar-link {{ request()->routeIs('user.identifiers.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-link"></i></div> <span>Linked identifiers</span></a>
                                <a href="{{ route('user.emails.index') }}" class="sidebar-link {{ request()->routeIs('user.emails.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></div> <span>Email history</span></a>
                            </div>
                        </div>
                        @endif

                    </nav>
                    {{-- ========== ACCOUNT (collapsible) — coins + assigned badges ==========
                         Mirror of the desktop section so the mobile drawer shows the
                         same wallet balance + account badges. Kept in lockstep. --}}
                    @php
                        // Task #3848 — coin balance moved to the header icon+badge.
                        $__mAcctBadges  = auth()->user()->accountBadges;
                        $__mShowAccount = $__mAcctBadges->isNotEmpty();
                    @endphp
                    @if($__mShowAccount)
                    <div class="px-3 pt-2" x-data="{
                            open: true,
                            init() {
                                try {
                                    const saved = localStorage.getItem('1inme_sidebar_account_open');
                                    if (saved !== null) this.open = saved === '1';
                                } catch (e) {}
                                this.$watch('open', (val) => {
                                    try { localStorage.setItem('1inme_sidebar_account_open', val ? '1' : '0'); } catch (e) {}
                                });
                            }
                        }">
                        <button type="button" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
                                class="sidebar-group-toggle pt-2 pb-1.5 px-1 text-[10px] font-bold uppercase tracking-[0.15em]">
                            <span>Account</span>
                            <i class="fas fa-chevron-down grp-chevron"></i>
                        </button>
                        <div x-show="open" x-cloak class="space-y-2 pt-1">
                            @if($__mAcctBadges->isNotEmpty())
                            <div class="px-1">
                                <p class="text-[9px] uppercase tracking-wider font-bold mb-1.5" style="color: var(--text-faint);">Your badges</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($__mAcctBadges as $badge)
                                    <span class="badge inline-flex items-center gap-1.5 text-[10px]"
                                          style="background: {{ $badge->color }}26; color: {{ $badge->color }}; border: 1px solid {{ $badge->color }}40;">
                                        <i class="fas fa-certificate text-[9px]"></i>{{ $badge->name }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                    <div class="p-3" style="border-top: 1px solid var(--border-subtle);">
                        @if(!session('impersonate_user_id') && auth()->user()->hasActiveAdminAccount())
                        <form action="{{ route('user.switch-to-admin') }}" method="POST" class="mb-2">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition-all"
                                    style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.25); color: var(--accent-light);">
                                <i class="fas fa-user-shield" style="font-size: 11px;"></i>
                                <span>Switch to admin</span>
                            </button>
                        </form>
                        @endif
                        <div class="flex items-center gap-2 mb-2">
                            @include('common.partials.theme-toggle')
                            <span class="text-[10px]" style="color: var(--text-dimmed);">Theme</span>
                        </div>
                    </div>
                </div>
            </div>

            <main class="flex-1 p-5 lg:p-6 overflow-y-auto">
                @include('common.partials.announcement-banner', ['surface' => 'dashboard'])
                @include('user.partials.verify-email-banner')
                @include('user.partials.starter-free-window-banner')
                @include('user.partials.cloud-connections-banner')
                @include('user.partials.custom-plan-offer-banner')
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
                    <div class="mb-4 p-3.5 rounded-xl text-blue-400 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(61,107,255,0.15); background: rgba(61,107,255,0.06);">
                        <i class="fas fa-info-circle"></i> {{ session('info') }}
                    </div>
                @endif
                {{-- "Claim your link" handle that couldn't be honored at sign-up.
                     Persisted (not flash) so it survives the OTP-verify hop and
                     shows once on the first authenticated page; pull() clears it. --}}
                @php($handleNotice = session()->pull('claimed_handle_notice'))
                @if(is_array($handleNotice) && !empty($handleNotice['requested']))
                    <div class="mb-4 p-3.5 rounded-xl text-amber-300 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(245,158,11,0.18); background: rgba(245,158,11,0.06);">
                        <i class="fas fa-circle-info"></i>
                        <span class="flex-1">
                            &#64;{{ $handleNotice['requested'] }} was already taken, so you're set up as
                            <span class="font-semibold">&#64;{{ $handleNotice['actual'] }}</span> for now.
                        </span>
                        <a href="{{ $handleNotice['url'] }}"
                           class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap"
                           style="border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.08); color: #fcd34d;">
                            <i class="fas fa-pen text-[9px]"></i> Change handle
                        </a>
                    </div>
                @endif

                @yield('content')

                <footer class="mt-10 pt-5 pb-2 text-[11px] flex flex-col sm:flex-row items-center justify-between gap-3"
                        style="border-top: 1px solid var(--border-glass); color: var(--text-dimmed);">
                    <div class="flex items-center gap-2">
                        <span>&copy; {{ date('Y') }} <span style="color: var(--text-muted); font-weight: 600;">Sayzio</span></span>
                        <span style="color: var(--border-glass-light);">•</span>
                        <span>All rights reserved</span>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Docs</a>
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Support</a>
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Privacy</a>
                        <a href="#" class="hover:text-[color:var(--accent)] transition-colors">Terms</a>
                    </div>
                    @include('common.partials.social-links-row', ['justify' => 'justify-end'])
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

    @if(\App\Services\Billing\WalletService::isEnabled())
    <script>
    // Keeps the sidebar coin badge (desktop + mobile drawer) fresh after a
    // balance-changing action — no full page reload.
    //   - window.refreshWalletBadge()                fetches the live balance.
    //   - dispatch 'wallet:refresh'                  re-fetches the balance.
    //   - dispatch 'wallet:balance' (detail.balance) sets it directly (broadcast).
    (function(){
        var URL = @json(route('user.wallet.balance'));
        function fmt(n){ return n >= 1000 ? (Math.round(n / 100) / 10) + 'k' : String(n); }
        // Task #3848 — badge is red when below the wallet's low-balance
        // threshold, green at/above it. `low` (when supplied by the JSON
        // response) wins; otherwise fall back to each badge's own
        // data-wallet-threshold so a plain balance push still recolors it.
        function paint(coins, low){
            var c = Number(coins) || 0;
            document.querySelectorAll('[data-wallet-badge]').forEach(function(el){
                el.textContent = fmt(c);
                el.setAttribute('title', c.toLocaleString('en-US') + ' coins');
                var isLow = low;
                if (typeof isLow === 'undefined') {
                    var threshold = Number(el.closest('[data-wallet-threshold]')?.getAttribute('data-wallet-threshold')) || 0;
                    isLow = threshold > 0 && c < threshold;
                }
                el.classList.toggle('bg-red-500', !!isLow);
                el.classList.toggle('bg-emerald-500', !isLow);
            });
            // Full, comma-formatted balance shown in the sidebar Account section.
            document.querySelectorAll('[data-wallet-amount]').forEach(function(el){
                el.textContent = c.toLocaleString('en-US');
            });
        }
        var inFlight = false;
        function refresh(){
            if (inFlight) return;
            inFlight = true;
            fetch(URL, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function(r){ return r.ok ? r.json() : null; })
                .then(function(d){ if (d && d.enabled && typeof d.balance !== 'undefined') paint(d.balance, d.low); })
                .catch(function(){})
                .finally(function(){ inFlight = false; });
        }
        window.refreshWalletBadge = refresh;
        window.addEventListener('wallet:refresh', refresh);
        window.addEventListener('wallet:balance', function(e){
            if (e && e.detail && typeof e.detail.balance !== 'undefined') paint(e.detail.balance, e.detail.low);
            else refresh();
        });
    })();
    </script>
    @endif

    @include('common.partials.site-assistant', ['surface' => 'app'])

    @include('user.partials.global-search-overlay')
    @include('common.partials.global-shortcuts')
    {{-- Voice agent: the full mic + agent now lives inside the Zio chat panel
         (common.partials.site-assistant) so there is ONE launcher on the
         dashboard. We still include this in non-floating mode so the reusable
         voiceDictation() helper + window.__voice config stay defined for the
         companion composer. --}}
    @include('partials.voice-assistant', ['voiceFloating' => false])
    @include('user.links.partials.themed-confirm')
    @include('common.partials.mini-profile-popover')
    @stack('scripts')
</body>
</html>
