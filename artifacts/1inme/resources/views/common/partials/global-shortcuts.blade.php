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
                ['My biolinks',        $__r('user.biolinks.index'),    'fa-solid fa-link'],
                ['Create new biolink', $__r('user.biolinks.create'),   'fa-solid fa-plus'],
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
@endphp

<div
    x-data="globalShortcuts()"
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
        class="fixed inset-0 z-[120] flex items-start justify-center px-4 pt-[12vh] pb-8 overflow-y-auto"
        style="background: rgba(7,7,15,0.78); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);"
        role="dialog"
        aria-modal="true"
        aria-label="Search 1INME"
    >
        <div
            x-show="isOpen"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            class="w-full max-w-xl rounded-2xl overflow-hidden border border-white/10 shadow-2xl"
            style="background: linear-gradient(180deg, rgba(20,20,32,0.96), rgba(13,13,20,0.98));"
            @click.stop
        >
            {{-- Search input --}}
            <div class="flex items-center gap-3 px-4 py-3 border-b border-white/10">
                <i class="fa-solid fa-magnifying-glass text-gray-500 text-sm"></i>
                <input
                    x-ref="searchInput"
                    x-model="query"
                    @keydown.down.prevent="moveSelection(1)"
                    @keydown.up.prevent="moveSelection(-1)"
                    @keydown.enter.prevent="goToSelection()"
                    type="text"
                    placeholder="{{ $__isLoggedIn ? 'Search your workspace…' : 'Search 1INME…' }}"
                    class="flex-1 bg-transparent border-0 outline-none text-white placeholder-gray-500 text-sm"
                    autocomplete="off"
                    spellcheck="false"
                >
                <kbd class="hidden sm:inline-flex items-center px-2 py-0.5 rounded bg-white/5 border border-white/10 text-[10px] font-bold text-gray-400">ESC</kbd>
            </div>

            {{-- Results --}}
            <div class="max-h-[55vh] overflow-y-auto p-2" x-ref="resultsBox">
                @php $__flatIndex = 0; @endphp
                @foreach($__shortcutGroups as $__group)
                    <div class="px-3 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 group-block" data-group="{{ $__group[0] }}">
                        {{ $__group[0] }}
                    </div>
                    @foreach($__group[1] as $__item)
                        <a
                            href="{{ $__item[1] }}"
                            data-search-name="{{ Str::lower($__item[0] . ' ' . $__group[0]) }}"
                            data-index="{{ $__flatIndex }}"
                            :class="selected === {{ $__flatIndex }} ? 'bg-white/10 border-white/15' : 'border-transparent hover:bg-white/5'"
                            class="search-row flex items-center gap-3 px-3 py-2.5 rounded-xl border text-sm text-gray-200 transition cursor-pointer"
                            @mouseenter="selected = {{ $__flatIndex }}"
                        >
                            <span class="w-7 h-7 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center text-[12px]" style="color:#1bd4d9">
                                <i class="{{ $__item[2] }}"></i>
                            </span>
                            <span class="flex-1 truncate">{{ $__item[0] }}</span>
                            <i class="fa-solid fa-arrow-turn-down-left text-[10px] text-gray-600 rotate-90 hidden sm:inline"></i>
                        </a>
                        @php $__flatIndex++; @endphp
                    @endforeach
                @endforeach

                <div
                    x-show="visibleCount === 0"
                    class="px-3 py-10 text-center text-sm text-gray-500"
                >
                    <i class="fa-regular fa-face-frown text-2xl mb-2 block opacity-60"></i>
                    No matches for “<span class="text-gray-300" x-text="query"></span>”
                </div>
            </div>

            {{-- Footer hint --}}
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-white/10 text-[11px] text-gray-500 bg-black/30 flex-wrap">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-300 text-[10px]">↑</kbd><kbd class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-300 text-[10px]">↓</kbd> navigate</span>
                    <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-300 text-[10px]">↵</kbd> open</span>
                    <span class="inline-flex items-center gap-1"><kbd class="px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-gray-300 text-[10px]">⌘ I</kbd> theme</span>
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
</style>

<script>
function globalShortcuts() {
    return {
        isOpen: false,
        query: '',
        selected: 0,
        visibleCount: 0,
        toastShown: false,
        isDarkNow: !document.documentElement.classList.contains('light-mode'),
        _toastTimer: null,

        init() {
            this.$watch('query', () => this.filter());
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
        },

        open() {
            this.isOpen = true;
            this.query = '';
            this.selected = 0;
        },

        close() { this.isOpen = false; },

        filter() {
            const q = (this.query || '').trim().toLowerCase();
            const rows = this.$refs.resultsBox.querySelectorAll('.search-row');
            const groups = this.$refs.resultsBox.querySelectorAll('.group-block');
            let visible = 0;
            const newIndexMap = [];
            rows.forEach((row) => {
                const name = row.dataset.searchName || '';
                const match = !q || name.indexOf(q) !== -1;
                row.dataset.hidden = match ? 'false' : 'true';
                if (match) {
                    newIndexMap.push(parseInt(row.dataset.index, 10));
                    visible++;
                }
            });
            this.visibleCount = visible;

            // hide group headers whose items are all filtered out
            groups.forEach((g) => {
                let any = false;
                let n = g.nextElementSibling;
                while (n && !n.classList.contains('group-block')) {
                    if (n.classList.contains('search-row') && n.dataset.hidden !== 'true') { any = true; break; }
                    n = n.nextElementSibling;
                }
                g.style.display = any ? '' : 'none';
            });

            // reset selection to first visible
            if (newIndexMap.length) {
                this.selected = newIndexMap[0];
            } else {
                this.selected = -1;
            }
        },

        moveSelection(dir) {
            const visibleRows = Array.from(this.$refs.resultsBox.querySelectorAll('.search-row'))
                .filter(r => r.dataset.hidden !== 'true');
            if (!visibleRows.length) return;
            const indexes = visibleRows.map(r => parseInt(r.dataset.index, 10));
            let cur = indexes.indexOf(this.selected);
            if (cur === -1) cur = 0;
            else cur = (cur + dir + indexes.length) % indexes.length;
            this.selected = indexes[cur];
            const row = visibleRows[cur];
            row && row.scrollIntoView({ block: 'nearest' });
        },

        goToSelection() {
            const row = this.$refs.resultsBox.querySelector('.search-row[data-index="' + this.selected + '"]');
            if (row && row.dataset.hidden !== 'true') {
                window.location.href = row.getAttribute('href');
            }
        },

        toggleTheme() {
            // Skip when an input/textarea/contenteditable is focused — let Cmd/Ctrl+I do its native thing
            const a = document.activeElement;
            if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.isContentEditable)) return;

            const isLight = document.documentElement.classList.toggle('light-mode');
            try { localStorage.setItem('1inme_theme', isLight ? 'light' : 'dark'); } catch (e) {}
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
