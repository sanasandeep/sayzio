<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    @include('common.partials.default-icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
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
        .sidebar-edge-toggle:hover { background: #3d6bff; color: #fff; border-color: #3d6bff; transform: scale(1.08); }
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

        /* ---- Help-note callout: light-mode text/icon legibility overrides ---- */
        html.light-mode .help-note-callout--info { color: #1e40af; border-color: rgba(59,130,246,.30); background-color: rgba(59,130,246,.07); }
        html.light-mode .help-note-callout--info .help-note-icon { color: #2563eb; }
        html.light-mode .help-note-callout--info a { color: #1d4ed8; }
        html.light-mode .help-note-callout--warn { color: #78350f; border-color: rgba(245,158,11,.30); background-color: rgba(245,158,11,.07); }
        html.light-mode .help-note-callout--warn .help-note-icon { color: #b45309; }
        html.light-mode .help-note-callout--warn a { color: #92400e; }
        html.light-mode .help-note-callout--tip { color: #064e3b; border-color: rgba(16,185,129,.30); background-color: rgba(16,185,129,.07); }
        html.light-mode .help-note-callout--tip .help-note-icon { color: #059669; }
        html.light-mode .help-note-callout--tip a { color: #065f46; }

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
<body class="min-h-screen" data-app-layout style="color: var(--text-primary);">
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
                        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center" aria-label="{{ config('app.name', 'Sayzio') }} admin">
                            @include('common.partials.brand-logo', ['height' => 'h-7'])
                        </a>
                        <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded" style="background: rgba(61,107,255,0.1); color: var(--accent-light);">Admin</span>
                    </div>
                    <button @click="mobileMenu = false" style="color: var(--text-muted);"><i class="fas fa-times text-sm"></i></button>
                </div>
                <nav class="flex-1 py-4 px-3 space-y-0.5 overflow-y-auto">
                    {{-- Overview --}}
                    <div class="section-header pt-1 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Overview</div>
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div><span>Dashboard</span></a>

                    {{-- People & Access --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">People &amp; Access</div>
                    <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') && ! request()->routeIs('admin.users.role-audit-exports.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-users"></i></div><span>Users</span></a>
                    <a href="{{ route('admin.staff.index') }}" class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-shield"></i></div><span>Staff</span></a>
                    <a href="{{ route('admin.roles.index') }}" class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-key"></i></div><span>Roles &amp; Permissions</span></a>
                    <a href="{{ route('admin.protected-accounts.index') }}" class="sidebar-link {{ request()->routeIs('admin.protected-accounts.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-shield-alt"></i></div><span>Protected accounts</span></a>
                    <a href="{{ route('admin.badges.index') }}" class="sidebar-link {{ request()->routeIs('admin.badges.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-certificate"></i></div><span>Account badges</span></a>
                    @if(auth('admin')->user()?->hasPermission('badge_requests.review'))
                    <a href="{{ route('admin.badge-requests.index') }}" class="sidebar-link {{ request()->routeIs('admin.badge-requests.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-award"></i></div><span>Badge requests</span></a>
                    @endif
                    <a href="{{ route('admin.users.activity-log.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.activity-log.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-clipboard-list"></i></div><span>Activity log</span></a>
                    <a href="{{ route('admin.privacy-requests.index') }}" class="sidebar-link {{ request()->routeIs('admin.privacy-requests.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-shield"></i></div><span>Privacy Requests</span></a>
                    @if(auth('admin')->user()?->isSuperAdmin())
                        <a href="{{ route('admin.users.role-audit-exports.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.role-audit-exports.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-file-csv"></i></div><span>Audit downloads</span></a>
                    @endif

                    {{-- Content & Links --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Content &amp; Links</div>
                    <a href="{{ route('admin.links.index') }}" class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-link"></i></div><span>All Links</span></a>
                    <a href="{{ route('admin.templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-layer-group"></i></div><span>Templates</span></a>
                    <a href="{{ route('admin.bg-templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.bg-templates.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-palette"></i></div><span>Background Templates</span></a>
                    <a href="{{ route('admin.onboarding-slides.index') }}" class="sidebar-link {{ request()->routeIs('admin.onboarding-slides.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-mobile-screen"></i></div><span>Onboarding Slides</span></a>
                    <a href="{{ route('admin.demo-content.index') }}" class="sidebar-link {{ request()->routeIs('admin.demo-content.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-seedling"></i></div><span>Demo Content</span></a>
                    <a href="{{ route('admin.assets.index') }}" class="sidebar-link {{ request()->routeIs('admin.assets.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-folder-tree"></i></div><span>Asset Vault</span></a>
                    <a href="{{ route('admin.site-pages.index') }}" class="sidebar-link {{ request()->routeIs('admin.site-pages.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-file-lines"></i></div><span>Site Pages</span></a>
                    @if(auth('admin')->user() && auth('admin')->user()->hasAnyPermission(['blogs.view','blogs.manage','blogs.publish','blogs.comments.moderate']))
                        <a href="{{ route('admin.blogs.posts.index') }}" class="sidebar-link {{ request()->routeIs('admin.blogs.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-feather-pointed"></i></div><span>Blog</span></a>
                    @endif

                    {{-- Moderation & Safety --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Moderation &amp; Safety</div>
                    <a href="{{ route('admin.biolink-reports.index') }}" class="sidebar-link {{ request()->routeIs('admin.biolink-reports.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-flag"></i></div><span>Link in Bio Reports</span></a>
                    <a href="{{ route('admin.moderation-queue.index') }}" class="sidebar-link {{ request()->routeIs('admin.moderation-queue.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-flag"></i></div><span>Reports &amp; DMCA</span></a>
                    <a href="{{ route('admin.adult-moderation.index') }}" class="sidebar-link {{ request()->routeIs('admin.adult-moderation.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-fire"></i></div><span>18+ moderation</span></a>
                    <a href="{{ route('admin.spam-rules.index') }}" class="sidebar-link {{ request()->routeIs('admin.spam-rules.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-shield-halved"></i></div><span>Spam Rules</span></a>
                    <a href="{{ route('admin.banned-names.index') }}" class="sidebar-link {{ request()->routeIs('admin.banned-names.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-ban"></i></div><span>Banned Names</span></a>
                    <a href="{{ route('admin.file-scan-queue.index') }}" class="sidebar-link {{ request()->routeIs('admin.file-scan-queue.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-shield-virus"></i></div><span>File Scans</span></a>
                    <a href="{{ route('admin.cookie-consent.edit') }}" class="sidebar-link {{ request()->routeIs('admin.cookie-consent.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-cookie-bite"></i></div><span>Cookie Consent</span></a>

                    {{-- Billing & Plans --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Billing &amp; Plans</div>
                    <a href="{{ route('admin.plans.index') }}" class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-tags"></i></div><span>Plans</span></a>
                    <a href="{{ route('admin.coin-packages.index') }}" class="sidebar-link {{ request()->routeIs('admin.coin-packages.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-coins"></i></div><span>Coin Packages</span></a>
                    <a href="{{ route('admin.wallet-settings.edit') }}" class="sidebar-link {{ request()->routeIs('admin.wallet-settings.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-wallet"></i></div><span>Wallet Settings</span></a>
                    <a href="{{ route('admin.addons.index') }}" class="sidebar-link {{ request()->routeIs('admin.addons.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-puzzle-piece"></i></div><span>Addons</span></a>
                    <a href="{{ route('admin.referrals.index') }}" class="sidebar-link {{ request()->routeIs('admin.referrals.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-gift"></i></div><span>Referrals</span></a>
                    <a href="{{ route('admin.starter-renewals.index') }}" class="sidebar-link {{ request()->routeIs('admin.starter-renewals.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-gift"></i></div><span>Renewal Reminders</span></a>

                    {{-- AI --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">AI</div>
                    <a href="{{ route('admin.ai-engine.edit') }}" class="sidebar-link {{ request()->routeIs('admin.ai-engine.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-brain"></i></div><span>AI Engine</span></a>
                    <a href="{{ route('admin.ai-usage.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-usage.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-microchip"></i></div><span>AI Usage</span></a>
                    <a href="{{ route('admin.ai-minds.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-minds.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-network-wired"></i></div><span>AI Knowledge Bases</span></a>
                    <a href="{{ route('admin.ai-personas.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-personas.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-user-astronaut"></i></div><span>AI Personas</span></a>
                    <a href="{{ route('admin.site-assistant.edit') }}" class="sidebar-link {{ request()->routeIs('admin.site-assistant.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-robot"></i></div><span>Site Assistant</span></a>
                    <a href="{{ route('admin.ask-coach.index') }}" class="sidebar-link {{ request()->routeIs('admin.ask-coach.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-comment-dots"></i></div><span>AI Coach</span></a>
                    <a href="{{ route('admin.ai-companions.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-companions.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-robot"></i></div><span>AI Companions</span></a>
                    <a href="{{ route('admin.coach-defaults.edit') }}" class="sidebar-link {{ request()->routeIs('admin.coach-defaults.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-wand-magic-sparkles"></i></div><span>Score Presets</span></a>

                    {{-- Marketing & Comms --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Marketing &amp; Comms</div>
                    <a href="{{ route('admin.marketing-settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.marketing-settings.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div><span>Marketing</span></a>
                    <a href="{{ route('admin.link-type-pairings.index') }}" class="sidebar-link {{ request()->routeIs('admin.link-type-pairings.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-object-group"></i></div><span>Perfect Pairings</span></a>
                    <a href="{{ route('admin.marketing-seo.index') }}" class="sidebar-link {{ request()->routeIs('admin.marketing-seo.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-magnifying-glass-chart"></i></div><span>Marketing SEO</span></a>
                    <a href="{{ route('admin.marketing-events.index') }}" class="sidebar-link {{ request()->routeIs('admin.marketing-events.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div><span>Marketing Events</span></a>
                    <a href="{{ route('admin.site-stats.index') }}" class="sidebar-link {{ request()->routeIs('admin.site-stats.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div><span>Marketing Stats</span></a>
                    <a href="{{ route('admin.testimonials.index') }}" class="sidebar-link {{ request()->routeIs('admin.testimonials.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-quote-right"></i></div><span>Testimonials</span></a>
                    <a href="{{ route('admin.announcements.index') }}" class="sidebar-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div><span>Announcements</span></a>
                    <a href="{{ route('admin.newsletter.index') }}" class="sidebar-link {{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></div><span>Newsletter</span></a>
                    <a href="{{ route('admin.notifications.index') }}" class="sidebar-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div><span>Notifications</span></a>
                    <a href="{{ route('admin.social-links.edit') }}" class="sidebar-link {{ request()->routeIs('admin.social-links.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-share-nodes"></i></div><span>Social Links</span></a>
                    <a href="{{ route('admin.contact-inbox.index') }}" class="sidebar-link {{ request()->routeIs('admin.contact-inbox.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-inbox"></i></div><span>Contact Inbox</span></a>

                    {{-- System --}}
                    <div class="section-header pt-4 pb-1 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">System</div>
                    <a href="{{ route('admin.domains.index') }}" class="sidebar-link {{ request()->routeIs('admin.domains.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-globe"></i></div><span>Domains</span></a>
                    <a href="{{ route('admin.integrations.index') }}" class="sidebar-link {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-puzzle-piece"></i></div><span>Integrations</span></a>
                    <a href="{{ route('admin.feature-states.index') }}" class="sidebar-link {{ request()->routeIs('admin.feature-states.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-clock"></i></div><span>Feature States</span></a>
                    <a href="{{ route('admin.email-templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.email-templates.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-envelopes-bulk"></i></div><span>Email Templates</span></a>
                    <a href="{{ route('admin.email-logs.index') }}" class="sidebar-link {{ request()->routeIs('admin.email-logs.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-paper-plane"></i></div><span>Email Log</span></a>
                    <a href="{{ route('admin.auth-settings.index') }}" class="sidebar-link {{ request()->routeIs('admin.auth-settings.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-right-to-bracket"></i></div><span>Login &amp; OTP</span></a>
                    <a href="{{ route('admin.email-verification-reminders.index') }}" class="sidebar-link {{ request()->routeIs('admin.email-verification-reminders.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-envelope-circle-check"></i></div><span>Verify Reminders</span></a>
                    <a href="{{ route('admin.maintenance.index') }}" class="sidebar-link {{ request()->routeIs('admin.maintenance.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-triangle-exclamation"></i></div><span>Maintenance Mode</span></a>
                    <a href="{{ route('admin.cron-jobs.index') }}" class="sidebar-link {{ request()->routeIs('admin.cron-jobs.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-clock"></i></div><span>Scheduled Jobs</span></a>
                    <a href="{{ route('admin.schema.repair-audits') }}" class="sidebar-link {{ request()->routeIs('admin.schema.repair-audits') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-wrench"></i></div><span>Schema Repairs</span></a>
                    <a href="{{ route('admin.stats-storage.index') }}" class="sidebar-link {{ request()->routeIs('admin.stats-storage.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-hard-drive"></i></div><span>Analytics Storage</span></a>
                    <a href="{{ route('admin.branding.edit') }}" class="sidebar-link {{ request()->routeIs('admin.branding.*') ? 'active' : '' }}"><div class="nav-icon-wrap"><i class="fas fa-palette"></i></div><span>Branding</span></a>
                </nav>
                @if(!session('impersonate_user_id') && auth()->guard('admin')->user()?->hasUserAccount())
                <div class="p-3" style="border-top: 1px solid var(--border-strong);">
                    <form action="{{ route('admin.switch-to-user') }}" method="POST">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition-all"
                                style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.25); color: var(--accent-light);">
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
                    <div class="mb-4 p-3.5 rounded-xl text-blue-400 text-xs font-medium flex items-center gap-2.5" style="border: 1px solid rgba(61,107,255,0.15); background: rgba(61,107,255,0.06);">
                        <i class="fas fa-info-circle"></i> {{ session('info') }}
                    </div>
                @endif

                @yield('content')

                <footer class="mt-10 pt-5 pb-2 text-[11px] flex flex-col sm:flex-row items-center justify-between gap-3"
                        style="border-top: 1px solid var(--border-glass); color: var(--text-dimmed);">
                    <div class="flex items-center gap-2">
                        <span>&copy; {{ date('Y') }} <span style="color: var(--text-muted); font-weight: 600;">Sayzio</span></span>
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
