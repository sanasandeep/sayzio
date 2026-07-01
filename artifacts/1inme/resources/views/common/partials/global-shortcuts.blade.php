{{--
    Global keyboard shortcuts + Cmd/Ctrl+K search modal.
    - Cmd/Ctrl + K  opens the "Search everything" modal
    - Cmd/Ctrl + I  toggles light/dark theme (same localStorage key as theme-toggle)
    Auth-aware: shows different actions when the user is logged in vs out.
--}}
@php
    $__isLoggedIn = auth()->check();
    $__user       = auth()->user();

    // Resolve a route by name, returning null if it doesn't exist on this install.
    $__r = function (string $name, string $hash = '') {
        if (!\Illuminate\Support\Facades\Route::has($name)) return null;
        try { return route($name) . $hash; } catch (\Throwable $e) { return null; }
    };

    if ($__isLoggedIn) {
        $__rawGroups = [
            ['Workspace', [
                ['Dashboard',          $__r('user.dashboard'),         'fa-solid fa-gauge-high'],
                ['My Link in Bio pages',        $__r('user.biolinks.index'),    'fa-solid fa-link'],
                ['Create new Link in Bio', $__r('user.biolinks.create'),   'fa-solid fa-plus'],
                ['Short links',        $__r('user.links.index'),       'fa-solid fa-bolt'],
                ['QR codes',           $__r('user.qr.index'),          'fa-solid fa-qrcode'],
                ['Forms',              $__r('user.forms.index'),       'fa-solid fa-square-poll-vertical'],
                ['Vault',              $__r('user.vault.index'),       'fa-solid fa-shield-halved'],
                ['Files',              $__r('user.files.index'),       'fa-solid fa-folder-open'],
            ]],
            ['Grow', [
                ['Inbox',              $__r('user.inbox.index'),       'fa-solid fa-inbox'],
                ['Subscribers',        $__r('user.subscribers.index'), 'fa-solid fa-users'],
                ['Followers',          $__r('user.followers.index'),   'fa-solid fa-user-group'],
                ['Contacts',           $__r('user.contacts.index'),    'fa-solid fa-address-book'],
                ['Events',             $__r('user.events.index'),      'fa-solid fa-calendar'],
            ]],
            ['Account', [
                ['Profile',            $__r('user.profile.edit'),      'fa-solid fa-user'],
                ['Billing & plan',     $__r('user.billing.show'),      'fa-solid fa-credit-card'],
                ['Upgrade',            $__r('user.upgrade'),           'fa-solid fa-rocket'],
                ['Workspaces',         $__r('user.workspaces.switch'), 'fa-solid fa-people-roof'],
                ['Sign out',           $__r('user.logout'),            'fa-solid fa-arrow-right-from-bracket'],
            ]],
        ];
    } else {
        $__rawGroups = [
            ['Explore', [
                ['Home',          $__r('home'),                 'fa-solid fa-house'],
                ['Features',      $__r('site.features'),        'fa-solid fa-star'],
                ['Use cases',     $__r('site.services'),        'fa-solid fa-briefcase'],
                ['How it works',  $__r('site.how-it-works'),    'fa-solid fa-circle-info'],
                ['Pricing',       $__r('home', '#pricing'),     'fa-solid fa-tags'],
                ['Discover',      $__r('site.discovery'),       'fa-solid fa-compass'],
                ['Creators feed', $__r('site.creators-feed'),   'fa-solid fa-rss'],
                ['Buzz',          $__r('site.buzz'),            'fa-solid fa-bolt-lightning'],
                ['Workspace & Team', $__r('site.workspace-team'), 'fa-solid fa-people-roof'],
            ]],
            ['Company', [
                ['About',         $__r('site.about'),    'fa-solid fa-building'],
                ['Contact',       $__r('site.contact'),  'fa-solid fa-envelope'],
                ['FAQs',          $__r('site.faqs'),     'fa-solid fa-circle-question'],
            ]],
            ['Legal', [
                ['Terms',         $__r('site.terms'),    'fa-solid fa-file-contract'],
                ['Privacy',       $__r('site.privacy'),  'fa-solid fa-user-shield'],
                ['Refunds',       $__r('site.refunds'),  'fa-solid fa-rotate-left'],
                ['Cookies',       $__r('site.cookies'),  'fa-solid fa-cookie-bite'],
                ['GDPR',          $__r('site.gdpr'),     'fa-solid fa-scale-balanced'],
            ]],
            ['Get started', [
                ['Log in',         $__r('login.page'),     'fa-solid fa-arrow-right-to-bracket'],
                ['Create account', $__r('register.page'),  'fa-solid fa-user-plus'],
            ]],
        ];
    }

    // Drop any items whose route is missing, and any group that ends up empty.
    $__shortcutGroups = [];
    foreach ($__rawGroups as $__g) {
        $__items = array_values(array_filter($__g[1], fn($i) => !empty($i[1])));
        if (!empty($__items)) {
            $__shortcutGroups[] = [$__g[0], $__items];
        }
    }

    // Live universal finder endpoint (Contacts / People / My links / Followed /
    // Workspaces). Only wired for logged-in users; the same server contract
    // (App\Modules\User\Support\DialerSearch) powers the dialer, REST + mobile.
    $__searchUrl = $__isLoggedIn ? $__r('user.dialer.search') : null;
@endphp

<div
    x-data="globalShortcuts({ searchUrl: @js($__searchUrl) })"
    x-init="init()"
    @keydown.window.prevent.stop.cmd.k="open()"
    @keydown.window.prevent.stop.ctrl.k="open()"
    @keydown.window.prevent.stop.cmd.i="toggleTheme()"
    @keydown.window.prevent.stop.ctrl.i="toggleTheme()"
    class="contents"
>
    {{-- Modal --}}
    <div
        x-cloak
        x-show="isOpen"
        x-transition.opacity
        @keydown.escape.window="close()"
        @click.self="close()"
        class="gsm-backdrop fixed inset-0 z-[120] flex items-start justify-center px-4 pt-[12vh] pb-8 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-label="Search Sayzio"
    >
        <div
            x-show="isOpen"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            class="gsm-panel w-full max-w-xl rounded-2xl overflow-hidden shadow-2xl"
            @click.stop
        >
            {{-- Search input --}}
            <div class="gsm-divider flex items-center gap-3 px-4 py-3 border-b">
                <i class="gsm-icon-muted fa-solid fa-magnifying-glass text-sm"></i>
                <input
                    x-ref="searchInput"
                    x-model="query"
                    @keydown.down.prevent="moveSelection(1)"
                    @keydown.up.prevent="moveSelection(-1)"
                    @keydown.enter.prevent="goToSelection()"
                    type="text"
                    placeholder="{{ $__isLoggedIn ? 'Search your workspace…' : 'Search Sayzio…' }}"
                    class="gsm-search-input flex-1 bg-transparent border-0 outline-none text-sm"
                    autocomplete="off"
                    spellcheck="false"
                >
                <kbd class="gsm-kbd hidden sm:inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold">ESC</kbd>
            </div>

            {{-- Results --}}
            <div class="max-h-[55vh] overflow-y-auto p-2" x-ref="resultsBox">
                {{-- Live universal finder results (Contacts / People / My links /
                     Followed / Workspaces). Populated client-side from
                     user.dialer.search; empty (and hidden) for logged-out users
                     or an empty query. --}}
                <div x-ref="dynBox"></div>

                {{-- Static nav shortcuts (always available, filtered client-side). --}}
                <div x-ref="staticBox">
                    @foreach($__shortcutGroups as $__group)
                        <div class="gsm-group-header px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider group-block" data-group="{{ $__group[0] }}">
                            {{ $__group[0] }}
                        </div>
                        @foreach($__group[1] as $__item)
                            <a
                                href="{{ $__item[1] }}"
                                data-search-name="{{ Str::lower($__item[0] . ' ' . $__group[0]) }}"
                                class="gsm-nav-row gsm-row search-row flex items-center gap-3 px-3 py-2.5 rounded-xl border text-sm transition cursor-pointer"
                            >
                                <span class="gsm-row-icon w-7 h-7 rounded-lg flex items-center justify-center text-[12px]">
                                    <i class="{{ $__item[2] }}"></i>
                                </span>
                                <span class="flex-1 truncate">{{ $__item[0] }}</span>
                                <i class="gsm-icon-faint fa-solid fa-arrow-turn-down-left text-[10px] rotate-90 hidden sm:inline"></i>
                            </a>
                        @endforeach
                    @endforeach
                </div>

                <div
                    x-show="loading"
                    x-cloak
                    class="gsm-empty px-3 py-6 text-center text-sm"
                >
                    <i class="fa-solid fa-spinner fa-spin text-lg mb-2 block opacity-60"></i>
                    Searching…
                </div>

                <div
                    x-show="!loading && visibleCount === 0"
                    class="gsm-empty px-3 py-10 text-center text-sm"
                >
                    <i class="fa-regular fa-face-frown text-2xl mb-2 block opacity-60"></i>
                    No matches for “<span class="gsm-empty-query" x-text="query"></span>”
                </div>
            </div>

            {{-- Footer hint --}}
            <div class="gsm-footer flex items-center justify-between gap-3 px-4 py-2.5 border-t text-[11px] flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="inline-flex items-center gap-1"><kbd class="gsm-kbd px-1.5 py-0.5 rounded text-[10px]">↑</kbd><kbd class="gsm-kbd px-1.5 py-0.5 rounded text-[10px]">↓</kbd> navigate</span>
                    <span class="inline-flex items-center gap-1"><kbd class="gsm-kbd px-1.5 py-0.5 rounded text-[10px]">↵</kbd> open</span>
                    <span class="inline-flex items-center gap-1"><kbd class="gsm-kbd px-1.5 py-0.5 rounded text-[10px]">⌘ I</kbd> theme</span>
                </div>
                <span class="hidden sm:inline">{{ $__isLoggedIn ? '✨ Logged in as ' . e($__user->name ?? $__user->email ?? 'you') : 'Sign in for personalised search' }}</span>
            </div>
        </div>
    </div>

    {{-- Toast (theme switched) --}}
    <div
        x-cloak
        x-show="toastShown"
        x-transition.opacity.duration.200ms
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[130] px-4 py-2.5 rounded-full text-sm font-semibold shadow-lg border border-white/10 flex items-center gap-2"
        style="background: rgba(20,20,32,0.95); backdrop-filter: blur(10px); color:#fff;"
    >
        <i :class="isDarkNow ? 'fa-solid fa-moon' : 'fa-solid fa-sun'" style="color:#1bd4d9"></i>
        <span x-text="isDarkNow ? 'Dark mode' : 'Light mode'"></span>
        <span class="text-gray-500 text-xs ml-1">⌘I</span>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
    .search-row[data-hidden="true"] { display: none; }

    /* ===== Global search / command palette modal (theme-aware) =====
       Self-contained copy of the modal's surface styles so the panel + backdrop
       render wherever this partial is included — including public/marketing
       surfaces that do NOT load common.partials.theme-styles. Every CSS variable
       carries a fallback (dark default / light override) so the modal looks right
       even when the theme tokens aren't defined on the page. On the authed
       user/admin layouts the tokens resolve and these rules match theme-styles. */
    .gsm-backdrop {
        background: rgba(7,7,15,0.78);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    html.light-mode .gsm-backdrop { background: var(--overlay-bg, rgba(7,20,55,0.28)); }

    .gsm-panel {
        background: linear-gradient(180deg, rgba(20,20,32,0.96), rgba(13,13,20,0.98));
        border: 1px solid var(--border-glass, rgba(255,255,255,0.10));
    }
    html.light-mode .gsm-panel {
        background: var(--bg-card, #ffffff);
        box-shadow: var(--card-shadow-hover, 0 6px 14px rgba(7,20,55,0.06));
    }

    .gsm-divider { border-color: var(--border-glass, rgba(255,255,255,0.10)); }
    html.light-mode .gsm-divider { border-color: var(--border-glass, #dbdfe9); }

    .gsm-search-input { color: var(--text-primary, #ffffff); }
    .gsm-search-input::placeholder { color: var(--text-dimmed, #64748b); opacity: 1; }
    html.light-mode .gsm-search-input { color: var(--text-primary, #071437); }
    html.light-mode .gsm-search-input::placeholder { color: var(--text-dimmed, #5e6884); }

    .gsm-icon-muted { color: var(--text-dimmed, #64748b); }
    .gsm-icon-faint { color: var(--text-faint, #475569); }
    html.light-mode .gsm-icon-muted { color: var(--text-dimmed, #5e6884); }
    html.light-mode .gsm-icon-faint { color: var(--text-faint, #6b7491); }

    .gsm-group-header { color: var(--text-dimmed, #64748b); }
    html.light-mode .gsm-group-header { color: var(--text-dimmed, #5e6884); }

    .gsm-row { color: var(--text-secondary, #e2e8f0); border-color: transparent; }
    .gsm-row:hover { background: var(--bg-glass-hover, rgba(255,255,255,0.06)); }
    .gsm-row.is-selected {
        background: var(--bg-glass-input-focus, rgba(255,255,255,0.07));
        border-color: var(--border-glass-light, rgba(255,255,255,0.16));
    }
    html.light-mode .gsm-row { color: var(--text-secondary, #252f4a); }
    html.light-mode .gsm-row:hover { background: var(--bg-glass-hover, #f4f5f9); }
    html.light-mode .gsm-row.is-selected {
        background: var(--c-primary-soft, #eaf0ff);
        border-color: var(--border-glass-light, #c4c8d3);
    }

    .gsm-row-icon {
        background: var(--bg-glass-input, rgba(255,255,255,0.04));
        border: 1px solid var(--border-glass, rgba(255,255,255,0.10));
        color: #7d9bff;
    }
    html.light-mode .gsm-row-icon {
        background: var(--bg-glass-input, #ffffff);
        border-color: var(--border-glass, #dbdfe9);
        color: var(--accent, #3d6bff);
    }

    .gsm-kbd {
        background: var(--bg-glass-input, rgba(255,255,255,0.04));
        border: 1px solid var(--border-glass, rgba(255,255,255,0.10));
        color: var(--text-muted, #94a3b8);
    }
    html.light-mode .gsm-kbd {
        background: var(--bg-glass-input, #ffffff);
        border-color: var(--border-glass, #dbdfe9);
        color: var(--text-muted, #4b5675);
    }

    .gsm-footer {
        color: var(--text-dimmed, #64748b);
        border-color: var(--border-glass, rgba(255,255,255,0.10));
        background: rgba(0,0,0,0.30);
    }
    html.light-mode .gsm-footer {
        color: var(--text-dimmed, #5e6884);
        border-color: var(--border-glass, #dbdfe9);
        background: var(--bg-glass-hover, #f4f5f9);
    }

    .gsm-empty { color: var(--text-dimmed, #64748b); }
    .gsm-empty-query { color: var(--text-secondary, #e2e8f0); }
    html.light-mode .gsm-empty { color: var(--text-dimmed, #5e6884); }
    html.light-mode .gsm-empty-query { color: var(--text-secondary, #252f4a); }
</style>

<script>
function globalShortcuts(cfg) {
    cfg = cfg || {};
    return {
        isOpen: false,
        query: '',
        selected: 0,
        visibleCount: 0,
        loading: false,
        toastShown: false,
        isDarkNow: !document.documentElement.classList.contains('light-mode'),
        _toastTimer: null,
        // Live universal finder (logged-in only).
        searchUrl: cfg.searchUrl || null,
        _searchTimer: null,
        _searchSeq: 0,

        init() {
            this.$watch('query', () => this.onQueryChange());
            this.$watch('isOpen', (v) => {
                if (v) {
                    document.body.style.overflow = 'hidden';
                    this.$nextTick(() => {
                        this.$refs.searchInput && this.$refs.searchInput.focus();
                        this.filter();
                    });
                } else {
                    document.body.style.overflow = '';
                }
            });

            // Hovering any row (static or dynamic) selects it.
            this.$refs.resultsBox.addEventListener('mousemove', (e) => {
                const row = e.target.closest('.gsm-nav-row');
                if (!row) return;
                const rows = this.visibleRows();
                const idx = rows.indexOf(row);
                if (idx !== -1 && idx !== this.selected) {
                    this.selected = idx;
                    this.highlight();
                }
            });

            // A visible trigger (e.g. the header search button) can open us.
            window.addEventListener('open-global-search', () => this.open());
        },

        open() {
            this.isOpen = true;
            this.query = '';
            this.selected = 0;
            this.loading = false;
            this.clearDynamic();
        },

        close() { this.isOpen = false; },

        // ── Query pipeline ───────────────────────────────────────────────
        onQueryChange() {
            this.filter();          // static shortcuts (instant, client-side)
            this.scheduleSearch();  // dynamic universal results (debounced)
        },

        // Filter the static nav shortcuts by substring, hide empty groups, and
        // recompute the unified selection over ALL currently-visible rows.
        filter() {
            const q = (this.query || '').trim().toLowerCase();
            const rows = this.$refs.staticBox.querySelectorAll('.search-row');
            const groups = this.$refs.staticBox.querySelectorAll('.group-block');
            rows.forEach((row) => {
                const name = row.dataset.searchName || '';
                const match = !q || name.indexOf(q) !== -1;
                row.dataset.hidden = match ? 'false' : 'true';
            });
            groups.forEach((g) => {
                let any = false;
                let n = g.nextElementSibling;
                while (n && !n.classList.contains('group-block')) {
                    if (n.classList.contains('search-row') && n.dataset.hidden !== 'true') { any = true; break; }
                    n = n.nextElementSibling;
                }
                g.style.display = any ? '' : 'none';
            });
            this.refreshSelection(true);
        },

        scheduleSearch() {
            clearTimeout(this._searchTimer);
            const q = (this.query || '').trim();
            if (!this.searchUrl || !q) {
                this._searchSeq++;   // invalidate any in-flight request
                this.loading = false;
                this.clearDynamic();
                this.refreshSelection(true);
                return;
            }
            this.loading = true;
            this._searchTimer = setTimeout(() => this.runSearch(q), 200);
        },

        async runSearch(q) {
            const seq = ++this._searchSeq;
            try {
                const params = new URLSearchParams({ q });
                const r = await fetch(this.searchUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (seq !== this._searchSeq) return; // superseded
                let groups = [];
                if (r.ok) {
                    const body = await r.json();
                    groups = (body && body.data && Array.isArray(body.data.groups)) ? body.data.groups : [];
                }
                if (seq !== this._searchSeq) return;
                this.renderDynamic(groups);
            } catch (e) {
                if (seq === this._searchSeq) this.clearDynamic();
            } finally {
                if (seq === this._searchSeq) {
                    this.loading = false;
                    this.refreshSelection(true);
                }
            }
        },

        clearDynamic() { if (this.$refs.dynBox) this.$refs.dynBox.innerHTML = ''; },

        _esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; },

        renderDynamic(groups) {
            if (!groups || !groups.length) { this.clearDynamic(); return; }
            this.$refs.dynBox.innerHTML = groups.map((g) => {
                const items = (g.items || []).map((it) => this.dynItemHtml(it)).join('');
                return `<div class="group-block gsm-group-header px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider">${this._esc(g.label)} <span style="opacity:.6">${(g.items || []).length}</span></div>${items}`;
            }).join('');
        },

        dynItemHtml(item) {
            const a = item.action || {};
            const badges = [];
            if (item.badge) badges.push(`<span class="px-1 rounded text-[9px] font-bold" style="background:rgba(236,72,153,.15);color:#f472b6">${this._esc(item.badge)}</span>`);
            if (item.verified) badges.push(`<span title="${this._esc(item.verified_label || 'Verified')}" style="color:#3d6bff"><i class="fas fa-check-circle text-[10px]"></i></span>`);
            const typeLabel = item.type_label
                ? `<span class="gsm-kbd px-1.5 py-0.5 rounded text-[9px] font-bold flex-shrink-0">${this._esc(item.type_label)}</span>` : '';
            const inner = `
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">${this._esc(item.initials)}</span>
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium truncate flex items-center gap-1">${this._esc(item.title)} ${badges.join(' ')}</span>
                    <span class="block text-[11px] truncate gsm-icon-muted">${this._esc(item.subtitle || '')}</span>
                </span>
                ${typeLabel}`;
            const cls = 'gsm-nav-row gsm-row flex items-center gap-3 px-3 py-2 rounded-xl border text-sm transition cursor-pointer';
            if (a.kind === 'workspace' && a.switch_url) {
                return `<button type="button" data-switch-url="${this._esc(a.switch_url)}" class="${cls} w-full text-left">${inner}</button>`;
            }
            if (a.url) {
                return `<a href="${this._esc(a.url)}" class="${cls}">${inner}</a>`;
            }
            return `<div class="${cls}" style="cursor:default;">${inner}</div>`;
        },

        // ── Unified selection over static + dynamic rows ─────────────────
        visibleRows() {
            return Array.from(this.$refs.resultsBox.querySelectorAll('.gsm-nav-row'))
                .filter(r => r.dataset.hidden !== 'true' && r.offsetParent !== null);
        },

        refreshSelection(resetToFirst) {
            const rows = this.visibleRows();
            this.visibleCount = rows.length;
            if (!rows.length) { this.selected = -1; return; }
            if (resetToFirst || this.selected < 0 || this.selected >= rows.length) {
                this.selected = 0;
            }
            this.highlight();
        },

        highlight() {
            const rows = this.visibleRows();
            rows.forEach((r, i) => r.classList.toggle('is-selected', i === this.selected));
            const row = rows[this.selected];
            row && row.scrollIntoView({ block: 'nearest' });
        },

        moveSelection(dir) {
            const rows = this.visibleRows();
            if (!rows.length) return;
            this.selected = (this.selected + dir + rows.length) % rows.length;
            this.highlight();
        },

        goToSelection() {
            const rows = this.visibleRows();
            const row = rows[this.selected];
            if (!row) return;
            const switchUrl = row.dataset.switchUrl;
            if (switchUrl) { this.switchWorkspace(switchUrl); return; }
            const href = row.getAttribute('href');
            if (href) { window.location.href = href; return; }
            row.click();
        },

        async switchWorkspace(url) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            try {
                await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            } catch (e) { /* ignore */ }
            window.location.reload();
        },

        toggleTheme() {
            // Skip when an input/textarea/contenteditable is focused — let Cmd/Ctrl+I do its native thing
            const a = document.activeElement;
            if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable)) return;

            var isLight;
            if (typeof window.inmeToggleTheme === 'function') {
                isLight = window.inmeToggleTheme();
            } else {
                isLight = document.documentElement.classList.toggle('light-mode');
                try { localStorage.setItem('1inme_theme', isLight ? 'light' : 'dark'); } catch (e) {}
                try { document.cookie = '1inme_theme=' + (isLight ? 'light' : 'dark') + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {}
            }
            this.isDarkNow = !isLight;

            // Sync any existing themeToggle() Alpine component
            try {
                document.querySelectorAll('[x-data*="themeToggle"]').forEach((el) => {
                    if (el.__x && el.__x.$data) el.__x.$data.isDark = !isLight;
                    if (window.Alpine && el._x_dataStack && el._x_dataStack[0]) el._x_dataStack[0].isDark = !isLight;
                });
            } catch (e) {}

            // Toast
            this.toastShown = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toastShown = false; }, 1600);
        },
    };
}
</script>
