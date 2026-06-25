<aside class="hidden lg:flex flex-col fixed inset-y-0 left-0 z-30 sidebar-v2 sidebar-shell"
       :class="sidebarMode === 'icons' ? 'collapsed' : ''"
       :style="'width:' + sidebarWidth + 'px; transform: translateX(' + (sidebarMode === 'hidden' ? '-100%' : '0') + '); pointer-events:' + (sidebarMode === 'hidden' ? 'none' : 'auto')"
       style="background: var(--bg-sidebar); backdrop-filter: none; -webkit-backdrop-filter: none;">

    {{-- Edge collapse handle --}}
    <button @click="setSidebar(sidebarMode === 'icons' ? 'full' : 'icons')"
            class="sidebar-edge-toggle"
            title="Toggle sidebar"
            aria-label="Toggle sidebar">
        <i class="fas" :class="sidebarMode === 'icons' ? 'fa-chevron-right' : 'fa-chevron-left'"></i>
    </button>

    <div class="flex items-center px-4" :class="sidebarMode === 'icons' ? 'justify-center' : 'justify-start'"
         style="height: 64px; border-bottom: 1px solid var(--border-strong);">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 group" :class="sidebarMode === 'icons' ? 'hidden' : ''">
            @include('common.partials.brand-logo', ['height' => 'h-8'])
            <span class="ml-1 text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded logo-text"
                  style="background: rgba(61,107,255,0.12); color: var(--accent-light); border: 1px solid rgba(61,107,255,0.2);">Admin</span>
        </a>
        <template x-if="sidebarMode === 'icons'">
            <a href="{{ route('admin.dashboard') }}" class="group" title="Sayzio Admin">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background: var(--accent);">
                    <span class="text-white text-sm font-bold">1</span>
                </div>
            </a>
        </template>
    </div>

    <nav class="flex-1 py-4 overflow-y-auto overflow-x-hidden sidebar-nav-scroll"
         :class="sidebarMode === 'icons' ? 'px-2' : 'px-3'">

        {{-- ============ Overview ============ --}}
        <div class="section-header pt-2 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Overview</div>

        <a href="{{ route('admin.dashboard') }}"
           class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
            <span class="nav-label">Dashboard</span>
            <span class="sidebar-tooltip">Dashboard</span>
        </a>

        {{-- ============ People & Access ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">People &amp; Access</div>

        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.users.*') && ! request()->routeIs('admin.users.role-audit-exports.*') ? 'active' : '' }}"
           style="--nav-tint:#3b82f6; --nav-tint-soft:rgba(59,130,246,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-users"></i></div>
            <span class="nav-label">Users</span>
            <span class="sidebar-tooltip">Users</span>
        </a>

        <a href="{{ route('admin.users.activity-log.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.users.activity-log.*') ? 'active' : '' }}"
           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-clipboard-list"></i></div>
            <span class="nav-label">Activity log</span>
            <span class="sidebar-tooltip">Activity log</span>
        </a>

        <a href="{{ route('admin.staff.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"
           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-user-shield"></i></div>
            <span class="nav-label">Staff</span>
            <span class="sidebar-tooltip">Staff</span>
        </a>

        <a href="{{ route('admin.roles.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
           style="--nav-tint:#eab308; --nav-tint-soft:rgba(234,179,8,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-key"></i></div>
            <span class="nav-label">Roles &amp; Permissions</span>
            <span class="sidebar-tooltip">Roles &amp; Permissions</span>
        </a>

        <a href="{{ route('admin.protected-accounts.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.protected-accounts.*') ? 'active' : '' }}"
           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-shield-alt"></i></div>
            <span class="nav-label">Protected accounts</span>
            <span class="sidebar-tooltip">Protected accounts</span>
        </a>

        <a href="{{ route('admin.privacy-requests.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.privacy-requests.*') ? 'active' : '' }}"
           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-user-shield"></i></div>
            <span class="nav-label">Privacy Requests</span>
            <span class="sidebar-tooltip">Privacy Requests</span>
        </a>

        @if(auth('admin')->user()?->isSuperAdmin())
            <a href="{{ route('admin.users.role-audit-exports.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.users.role-audit-exports.*') ? 'active' : '' }}"
               style="--nav-tint:#64748b; --nav-tint-soft:rgba(100,116,139,0.12);">
                <div class="nav-icon-wrap"><i class="fas fa-file-csv"></i></div>
                <span class="nav-label">Audit downloads</span>
                <span class="sidebar-tooltip">Audit downloads</span>
            </a>
        @endif

        {{-- ============ Content & Links ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Content &amp; Links</div>

        <a href="{{ route('admin.links.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.links.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-link"></i></div>
            <span class="nav-label">All Links</span>
            <span class="sidebar-tooltip">All Links</span>
        </a>

        <a href="{{ route('admin.templates.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.templates.*') ? 'active' : '' }}"
           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-layer-group"></i></div>
            <span class="nav-label">Templates</span>
            <span class="sidebar-tooltip">Templates</span>
        </a>

        <a href="{{ route('admin.bg-templates.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.bg-templates.*') ? 'active' : '' }}"
           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-palette"></i></div>
            <span class="nav-label">Background Templates</span>
            <span class="sidebar-tooltip">Background Templates</span>
        </a>

        <a href="{{ route('admin.onboarding-slides.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.onboarding-slides.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-mobile-screen"></i></div>
            <span class="nav-label">Onboarding Slides</span>
            <span class="sidebar-tooltip">Onboarding Slides</span>
        </a>

        <a href="{{ route('admin.demo-content.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.demo-content.*') ? 'active' : '' }}"
           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-seedling"></i></div>
            <span class="nav-label">Demo Content</span>
            <span class="sidebar-tooltip">Demo Content</span>
        </a>

        <a href="{{ route('admin.assets.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.assets.*') ? 'active' : '' }}"
           style="--nav-tint:#3d6bff; --nav-tint-soft:rgba(61,107,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-folder-tree"></i></div>
            <span class="nav-label">Asset Vault</span>
            <span class="sidebar-tooltip">Asset Vault</span>
        </a>

        <a href="{{ route('admin.site-pages.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.site-pages.*') ? 'active' : '' }}"
           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-file-lines"></i></div>
            <span class="nav-label">Site Pages</span>
            <span class="sidebar-tooltip">Site Pages</span>
        </a>

        @if(auth('admin')->user() && auth('admin')->user()->hasAnyPermission(['blogs.view','blogs.manage','blogs.publish','blogs.comments.moderate']))
            <a href="{{ route('admin.blogs.posts.index') }}"
               class="sidebar-link {{ request()->routeIs('admin.blogs.*') ? 'active' : '' }}"
               style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
                <div class="nav-icon-wrap"><i class="fas fa-feather-pointed"></i></div>
                <span class="nav-label">Blog</span>
                <span class="sidebar-tooltip">Blog</span>
            </a>
        @endif

        {{-- ============ Moderation & Safety ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Moderation &amp; Safety</div>

        <a href="{{ route('admin.biolink-reports.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.biolink-reports.*') ? 'active' : '' }}"
           style="--nav-tint:#dc2626; --nav-tint-soft:rgba(220,38,38,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-flag"></i></div>
            <span class="nav-label">Link in Bio Reports</span>
            <span class="sidebar-tooltip">Link in Bio Reports</span>
        </a>

        <a href="{{ route('admin.moderation-queue.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.moderation-queue.*') ? 'active' : '' }}"
           style="--nav-tint:#f43f5e; --nav-tint-soft:rgba(244,63,94,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-flag"></i></div>
            <span class="nav-label">Reports &amp; DMCA</span>
            <span class="sidebar-tooltip">Reports &amp; DMCA</span>
        </a>

        <a href="{{ route('admin.adult-moderation.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.adult-moderation.*') ? 'active' : '' }}"
           style="--nav-tint:#f97316; --nav-tint-soft:rgba(249,115,22,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-fire"></i></div>
            <span class="nav-label">18+ moderation</span>
            <span class="sidebar-tooltip">18+ moderation</span>
        </a>

        <a href="{{ route('admin.spam-rules.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.spam-rules.*') ? 'active' : '' }}"
           style="--nav-tint:#ef4444; --nav-tint-soft:rgba(239,68,68,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-shield-halved"></i></div>
            <span class="nav-label">Spam Rules</span>
            <span class="sidebar-tooltip">Spam Rules</span>
        </a>

        <a href="{{ route('admin.banned-names.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.banned-names.*') ? 'active' : '' }}"
           style="--nav-tint:#ef4444; --nav-tint-soft:rgba(239,68,68,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-ban"></i></div>
            <span class="nav-label">Banned Names</span>
            <span class="sidebar-tooltip">Banned Names</span>
        </a>

        <a href="{{ route('admin.file-scan-queue.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.file-scan-queue.*') ? 'active' : '' }}"
           style="--nav-tint:#f97316; --nav-tint-soft:rgba(249,115,22,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-shield-virus"></i></div>
            <span class="nav-label">File Scans</span>
            <span class="sidebar-tooltip">File Scan Queue</span>
        </a>

        <a href="{{ route('admin.cookie-consent.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.cookie-consent.*') ? 'active' : '' }}"
           style="--nav-tint:#eab308; --nav-tint-soft:rgba(234,179,8,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-cookie-bite"></i></div>
            <span class="nav-label">Cookie Consent</span>
            <span class="sidebar-tooltip">Cookie Consent</span>
        </a>

        {{-- ============ Billing & Plans ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Billing &amp; Plans</div>

        <a href="{{ route('admin.plans.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}"
           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-tags"></i></div>
            <span class="nav-label">Plans</span>
            <span class="sidebar-tooltip">Plans</span>
        </a>

        <a href="{{ route('admin.coin-packages.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.coin-packages.*') ? 'active' : '' }}"
           style="--nav-tint:#eab308; --nav-tint-soft:rgba(234,179,8,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-coins"></i></div>
            <span class="nav-label">Coin Packages</span>
            <span class="sidebar-tooltip">Coin Packages</span>
        </a>

        <a href="{{ route('admin.wallet-settings.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.wallet-settings.*') ? 'active' : '' }}"
           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-wallet"></i></div>
            <span class="nav-label">Wallet Settings</span>
            <span class="sidebar-tooltip">Wallet Settings</span>
        </a>

        <a href="{{ route('admin.addons.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.addons.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-puzzle-piece"></i></div>
            <span class="nav-label">Addons</span>
            <span class="sidebar-tooltip">Addons</span>
        </a>

        <a href="{{ route('admin.referrals.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.referrals.*') ? 'active' : '' }}"
           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-gift"></i></div>
            <span class="nav-label">Referrals</span>
            <span class="sidebar-tooltip">Referrals</span>
        </a>

        <a href="{{ route('admin.starter-renewals.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.starter-renewals.*') ? 'active' : '' }}"
           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-gift"></i></div>
            <span class="nav-label">Renewal Reminders</span>
            <span class="sidebar-tooltip">Free Renewal Reminders</span>
        </a>

        {{-- ============ AI ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">AI</div>

        <a href="{{ route('admin.ai-engine.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.ai-engine.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-brain"></i></div>
            <span class="nav-label">AI Engine</span>
            <span class="sidebar-tooltip">AI Engine</span>
        </a>

        <a href="{{ route('admin.ai-usage.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.ai-usage.*') ? 'active' : '' }}"
           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-microchip"></i></div>
            <span class="nav-label">AI Usage</span>
            <span class="sidebar-tooltip">AI Usage</span>
        </a>

        <a href="{{ route('admin.ai-minds.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.ai-minds.*') ? 'active' : '' }}"
           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-network-wired"></i></div>
            <span class="nav-label">AI Minds</span>
            <span class="sidebar-tooltip">AI Minds</span>
        </a>

        <a href="{{ route('admin.ai-personas.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.ai-personas.*') ? 'active' : '' }}"
           style="--nav-tint:#5c83ff; --nav-tint-soft:rgba(92,131,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-user-astronaut"></i></div>
            <span class="nav-label">AI Personas</span>
            <span class="sidebar-tooltip">AI Personas</span>
        </a>

        <a href="{{ route('admin.site-assistant.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.site-assistant.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-robot"></i></div>
            <span class="nav-label">Site Assistant</span>
            <span class="sidebar-tooltip">Site Assistant</span>
        </a>

        <a href="{{ route('admin.ask-coach.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.ask-coach.*') ? 'active' : '' }}"
           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-comment-dots"></i></div>
            <span class="nav-label">Ask Coach</span>
            <span class="sidebar-tooltip">Ask Coach</span>
        </a>

        <a href="{{ route('admin.ai-companions.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.ai-companions.*') ? 'active' : '' }}"
           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-robot"></i></div>
            <span class="nav-label">AI Companions</span>
            <span class="sidebar-tooltip">AI Companions</span>
        </a>

        <a href="{{ route('admin.coach-defaults.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.coach-defaults.*') ? 'active' : '' }}"
           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-wand-magic-sparkles"></i></div>
            <span class="nav-label">Score Presets</span>
            <span class="sidebar-tooltip">Score Presets</span>
        </a>

        {{-- ============ Marketing & Comms ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">Marketing &amp; Comms</div>

        <a href="{{ route('admin.marketing-settings.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.marketing-settings.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
            <span class="nav-label">Marketing</span>
            <span class="sidebar-tooltip">Marketing</span>
        </a>

        <a href="{{ route('admin.marketing-seo.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.marketing-seo.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-magnifying-glass-chart"></i></div>
            <span class="nav-label">Marketing SEO</span>
            <span class="sidebar-tooltip">Marketing SEO</span>
        </a>

        <a href="{{ route('admin.marketing-events.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.marketing-events.*') ? 'active' : '' }}"
           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
            <span class="nav-label">Marketing Events</span>
            <span class="sidebar-tooltip">Marketing Events</span>
        </a>

        <a href="{{ route('admin.site-stats.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.site-stats.*') ? 'active' : '' }}"
           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-chart-line"></i></div>
            <span class="nav-label">Marketing Stats</span>
            <span class="sidebar-tooltip">Marketing Stats</span>
        </a>

        <a href="{{ route('admin.testimonials.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.testimonials.*') ? 'active' : '' }}"
           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-quote-right"></i></div>
            <span class="nav-label">Testimonials</span>
            <span class="sidebar-tooltip">Testimonials</span>
        </a>

        <a href="{{ route('admin.announcements.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
            <span class="nav-label">Announcements</span>
            <span class="sidebar-tooltip">Announcements</span>
        </a>

        <a href="{{ route('admin.newsletter.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.newsletter.*') ? 'active' : '' }}"
           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></div>
            <span class="nav-label">Newsletter</span>
            <span class="sidebar-tooltip">Newsletter</span>
        </a>

        <a href="{{ route('admin.notifications.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></div>
            <span class="nav-label">Notifications</span>
            <span class="sidebar-tooltip">Notifications</span>
        </a>

        <a href="{{ route('admin.social-links.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.social-links.*') ? 'active' : '' }}"
           style="--nav-tint:#ec4899; --nav-tint-soft:rgba(236,72,153,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-share-nodes"></i></div>
            <span class="nav-label">Social Links</span>
            <span class="sidebar-tooltip">Social Links</span>
        </a>

        <a href="{{ route('admin.contact-inbox.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.contact-inbox.*') ? 'active' : '' }}"
           style="--nav-tint:#f97316; --nav-tint-soft:rgba(249,115,22,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-inbox"></i></div>
            <span class="nav-label">Contact Inbox</span>
            <span class="sidebar-tooltip">Contact Inbox</span>
        </a>

        {{-- ============ System ============ --}}
        <div class="section-header pt-5 pb-1.5 px-3 text-[10px] font-bold uppercase tracking-[0.15em]" style="color: var(--text-faint);">System</div>

        <a href="{{ route('admin.domains.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.domains.*') ? 'active' : '' }}"
           style="--nav-tint:#0ea5e9; --nav-tint-soft:rgba(14,165,233,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-globe"></i></div>
            <span class="nav-label">Domains</span>
            <span class="sidebar-tooltip">Domains</span>
        </a>

        <a href="{{ route('admin.integrations.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}"
           style="--nav-tint:#14b8a6; --nav-tint-soft:rgba(20,184,166,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-puzzle-piece"></i></div>
            <span class="nav-label">Integrations</span>
            <span class="sidebar-tooltip">Integrations</span>
        </a>

        <a href="{{ route('admin.auth-settings.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.auth-settings.*') ? 'active' : '' }}"
           style="--nav-tint:#22c55e; --nav-tint-soft:rgba(34,197,94,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-right-to-bracket"></i></div>
            <span class="nav-label">Login &amp; OTP</span>
            <span class="sidebar-tooltip">Login &amp; OTP</span>
        </a>

        @if(auth('admin')->user()?->isSuperAdmin())
        <a href="{{ route('admin.master-password.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.master-password.*') ? 'active' : '' }}"
           style="--nav-tint:#6e61ff; --nav-tint-soft:rgba(110,97,255,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-key"></i></div>
            <span class="nav-label">Master Password</span>
            <span class="sidebar-tooltip">Master Password</span>
        </a>
        @endif

        <a href="{{ route('admin.email-verification-reminders.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.email-verification-reminders.*') ? 'active' : '' }}"
           style="--nav-tint:#10b981; --nav-tint-soft:rgba(16,185,129,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-envelope-circle-check"></i></div>
            <span class="nav-label">Verify Reminders</span>
            <span class="sidebar-tooltip">Verification Reminders</span>
        </a>

        <a href="{{ route('admin.maintenance.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.maintenance.*') ? 'active' : '' }}"
           style="--nav-tint:#f59e0b; --nav-tint-soft:rgba(245,158,11,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-triangle-exclamation"></i></div>
            <span class="nav-label">Maintenance Mode</span>
            <span class="sidebar-tooltip">Maintenance Mode</span>
        </a>

        <a href="{{ route('admin.cron-jobs.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.cron-jobs.*') ? 'active' : '' }}"
           style="--nav-tint:#22d3ee; --nav-tint-soft:rgba(34,211,238,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-clock"></i></div>
            <span class="nav-label">Cron Jobs</span>
            <span class="sidebar-tooltip">Cron Jobs</span>
        </a>

        <a href="{{ route('admin.schema.repair-audits') }}"
           class="sidebar-link {{ request()->routeIs('admin.schema.repair-audits') ? 'active' : '' }}"
           style="--nav-tint:#f97316; --nav-tint-soft:rgba(249,115,22,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-wrench"></i></div>
            <span class="nav-label">Schema Repairs</span>
            <span class="sidebar-tooltip">Schema Repairs</span>
        </a>

        <a href="{{ route('admin.branding.edit') }}"
           class="sidebar-link {{ request()->routeIs('admin.branding.*') ? 'active' : '' }}"
           style="--nav-tint:#06b6d4; --nav-tint-soft:rgba(6,182,212,0.12);">
            <div class="nav-icon-wrap"><i class="fas fa-palette"></i></div>
            <span class="nav-label">Branding</span>
            <span class="sidebar-tooltip">Branding</span>
        </a>
    </nav>

    @php $__switchUser = !session('impersonate_user_id') && auth()->guard('admin')->user()?->hasUserAccount(); @endphp
    @if($__switchUser)
    <div class="px-3 pt-3" x-show="sidebarMode !== 'icons'" x-cloak>
        <form action="{{ route('admin.switch-to-user') }}" method="POST">
            @csrf
            <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold transition-all"
                    style="background: rgba(61,107,255,0.12); border: 1px solid rgba(61,107,255,0.25); color: var(--accent-light);"
                    title="Switch back to your user dashboard">
                <i class="fas fa-arrow-right-arrow-left" style="font-size: 11px;"></i>
                <span>Switch back to user</span>
            </button>
        </form>
    </div>
    <div class="px-3 pt-3 flex justify-center" x-show="sidebarMode === 'icons'" x-cloak>
        <form action="{{ route('admin.switch-to-user') }}" method="POST">
            @csrf
            <button type="submit" class="logout-btn" title="Switch back to user dashboard" style="color: var(--accent-light);">
                <i class="fas fa-arrow-right-arrow-left text-xs"></i>
            </button>
        </form>
    </div>
    @endif

    {{-- Footer / user --}}
    <div class="px-3 py-3" style="border-top: 1px solid var(--border-strong);">
        <div class="flex items-center gap-3"
             :class="sidebarMode === 'icons' ? 'justify-center' : ''">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                 style="background: linear-gradient(135deg,#5c83ff,#3d6bff);">
                {{ strtoupper(substr(auth()->guard('admin')->user()->name ?? 'A', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0 user-info">
                <p class="text-xs font-semibold truncate" style="color: var(--text-primary);">{{ auth()->guard('admin')->user()->name ?? 'Admin' }}</p>
                <p class="text-[10px] truncate" style="color: var(--text-dimmed);">{{ auth()->guard('admin')->user()->role->name ?? 'Super Admin' }}</p>
            </div>
            <form action="{{ route('admin.logout') }}" method="POST" class="user-info">
                @csrf
                <button type="submit" class="logout-btn" title="Log out">
                    <i class="fas fa-sign-out-alt text-[11px]"></i>
                </button>
            </form>
        </div>
    </div>
</aside>
