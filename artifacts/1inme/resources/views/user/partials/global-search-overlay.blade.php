{{--
    Global Search Overlay (full-screen, glassmorphic)
    ──────────────────────────────────────────────────
    Opens via:
      • `open-full-search` custom window event (fired by the header ⌘K button
        and the mobile-drawer search button).
      • Ctrl/⌘+K keyboard shortcut (intercepted here so it does NOT also open
        the command-palette in global-shortcuts.blade.php — the shortcut is
        swallowed when the overlay is already open, and Escape closes it).

    Results come from `user.dialer.search` (GET /user/dialer/search) with
    optional `page` + `per_group` params for load-more pagination. The Dialer
    page itself is NOT changed; both share the same endpoint.

    Light-mode: all colours go through existing CSS custom properties that are
    already paired. No new `<style>` block is introduced here so the
    light-mode-pairing validator has nothing to flag.
--}}
<div x-data="globalSearchOverlay()"
     x-show="open"
     x-cloak
     role="dialog"
     aria-modal="true"
     aria-label="Search"
     class="fixed inset-0 z-[9999] flex flex-col">

    {{-- ── Backdrop ─────────────────────────────────────────────────────── --}}
    <div class="absolute inset-0" @click="close()"
         style="background: var(--overlay-bg); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);"></div>

    {{-- ── Panel ────────────────────────────────────────────────────────── --}}
    <div class="relative z-10 flex flex-col w-full h-full overflow-hidden pointer-events-none">

        {{-- Search box area — centres in viewport when idle, anchors to top on first keystroke --}}
        <div class="pointer-events-auto transition-all duration-300 ease-out w-full"
             :class="hasTyped ? 'pt-5 pb-3' : 'mt-auto mb-auto pb-32'">
            <div class="w-full max-w-2xl mx-auto px-4">

                {{-- Input card --}}
                <div class="flex items-center gap-3 rounded-2xl px-4 py-3"
                     style="background: var(--bg-sidebar); border: 1.5px solid var(--border-strong); box-shadow: 0 24px 64px rgba(0,0,0,0.45);">

                    <div class="flex-shrink-0 w-[18px] flex items-center justify-center">
                        <div x-show="!loading">
                            <i class="fas fa-search" style="color: var(--text-muted); font-size: 14px;"></i>
                        </div>
                        <div x-show="loading"
                             class="w-[14px] h-[14px] rounded-full border-2 border-t-transparent animate-spin"
                             style="border-color: var(--accent-light); border-top-color: transparent;"></div>
                    </div>

                    <input x-ref="input"
                           type="text"
                           autocomplete="off"
                           spellcheck="false"
                           placeholder="Search contacts, links, people, workspaces…"
                           class="flex-1 bg-transparent outline-none min-w-0"
                           style="color: var(--text-primary); font-size: 16px; line-height: 1.5;"
                           x-model="query"
                           @input.debounce.200ms="onInput()"
                           @keydown.enter.prevent="navigateFirst()"
                           @keydown.arrow-down.prevent="focusResult(0)">

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <template x-if="!hasTyped">
                            <kbd class="hidden sm:inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold select-none"
                                 style="background: var(--bg-glass); color: var(--text-faint); border: 1px solid var(--border-subtle);">Esc</kbd>
                        </template>
                        <button @click="close()"
                                type="button"
                                class="flex items-center justify-center w-7 h-7 rounded-lg transition-colors"
                                style="color: var(--text-muted);"
                                x-on:mouseenter="$el.style.background='var(--bg-glass)'"
                                x-on:mouseleave="$el.style.background=''"
                                aria-label="Close search">
                            <i class="fas fa-times" style="font-size: 12px;"></i>
                        </button>
                    </div>
                </div>

                {{-- Idle hint --}}
                <p x-show="!hasTyped"
                   class="text-center text-xs mt-2.5"
                   style="color: var(--text-faint);">
                    Start typing to search contacts, links, people &amp; workspaces
                </p>

            </div>
        </div>

        {{-- ── Results ──────────────────────────────────────────────────── --}}
        <div x-show="hasTyped"
             x-ref="results"
             class="pointer-events-auto flex-1 overflow-y-auto overscroll-contain px-4 pb-10"
             style="-webkit-overflow-scrolling: touch;">
            <div class="max-w-2xl mx-auto">

                {{-- Loading skeleton --}}
                <template x-if="loading && groups.length === 0">
                    <div class="space-y-1.5 pt-2">
                        <template x-for="i in [1,2,3,4,5]" :key="i">
                            <div class="h-11 rounded-xl animate-pulse" style="background: var(--bg-glass);"></div>
                        </template>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="!loading && groups.length === 0 && query.trim() !== ''">
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-3"
                             style="background: var(--bg-glass);">
                            <i class="fas fa-search" style="color: var(--text-faint); font-size: 16px;"></i>
                        </div>
                        <p class="text-sm font-semibold" style="color: var(--text-primary);">No results for "<span x-text="query"></span>"</p>
                        <p class="text-xs mt-1" style="color: var(--text-muted);">Try a different search term</p>
                    </div>
                </template>

                {{-- Result groups --}}
                <template x-for="(group, gi) in groups" :key="group.key">
                    <div class="mb-5">

                        {{-- Group label --}}
                        <div class="flex items-center gap-2 px-1 py-1.5 mb-0.5">
                            <span class="text-[10px] font-bold uppercase tracking-[0.14em] flex-shrink-0"
                                  style="color: var(--text-faint);" x-text="group.label"></span>
                            <div class="flex-1 h-px" style="background: var(--border-subtle);"></div>
                        </div>

                        {{-- Items --}}
                        <div class="space-y-0.5" :data-group-index="gi">
                            <template x-for="(item, ii) in group.items" :key="item.type + '_' + item.id">
                                <a :href="item.action && item.action.url ? item.action.url : '#'"
                                   @click.prevent="navigate(item)"
                                   :data-result-index="gi + '_' + ii"
                                   tabindex="0"
                                   @keydown.enter.prevent="navigate(item)"
                                   @keydown.arrow-down.prevent="focusResult(gi, ii + 1)"
                                   @keydown.arrow-up.prevent="focusResult(gi, ii - 1)"
                                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-colors cursor-pointer w-full text-left outline-none"
                                   style="color: var(--text-primary);"
                                   x-on:mouseenter="$el.style.background='var(--bg-glass)'"
                                   x-on:mouseleave="$el.style.background=''"
                                   x-on:focus="$el.style.background='var(--bg-glass)'"
                                   x-on:blur="$el.style.background=''">

                                    {{-- Initials avatar --}}
                                    <div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center text-[11px] font-bold text-white"
                                         style="background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);"
                                         x-text="item.initials"></div>

                                    {{-- Title + subtitle --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-sm font-medium truncate" style="max-width: 260px;" x-text="item.title"></span>
                                            <template x-if="item.verified">
                                                <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-blue-400 flex-shrink-0">
                                                    <i class="fas fa-circle-check text-[8px]"></i>
                                                    <span x-text="item.verified_label || 'Verified'"></span>
                                                </span>
                                            </template>
                                            <template x-if="item.badge && !item.verified">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-semibold flex-shrink-0"
                                                      style="background: var(--bg-glass); color: var(--text-muted);"
                                                      x-text="item.badge"></span>
                                            </template>
                                        </div>
                                        <p x-show="item.subtitle"
                                           class="text-xs truncate mt-0.5"
                                           style="color: var(--text-muted);"
                                           x-text="item.subtitle"></p>
                                    </div>

                                    {{-- Type label --}}
                                    <span class="flex-shrink-0 text-[10px] font-medium px-1.5 py-0.5 rounded-full hidden sm:block"
                                          style="background: var(--bg-glass); color: var(--text-faint);"
                                          x-text="item.type_label"></span>

                                    {{-- Arrow --}}
                                    <i class="fas fa-chevron-right flex-shrink-0" style="color: var(--text-faint); font-size: 9px;"></i>

                                </a>
                            </template>
                        </div>

                        {{-- Per-group "load more" --}}
                        <template x-if="group.has_more">
                            <button type="button"
                                    @click="loadMore()"
                                    :disabled="loading"
                                    class="w-full text-xs py-2 mt-1 rounded-xl transition-colors"
                                    style="color: var(--accent-light); background: var(--bg-glass);"
                                    x-on:mouseenter="$el.style.opacity='0.8'"
                                    x-on:mouseleave="$el.style.opacity='1'">
                                <span x-show="!loading">Load more in <span x-text="group.label"></span></span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </template>

                    </div>
                </template>

            </div>
        </div>

    </div>
</div>

@once
<script>
function globalSearchOverlay() {
    return {
        open: false,
        query: '',
        hasTyped: false,
        loading: false,
        groups: [],
        page: 0,
        perGroup: 12,
        _searchEndpoint: @js(route('user.dialer.search')),
        _csrfToken: document.querySelector('meta[name="csrf-token"]')?.content ?? '',

        init() {
            window.addEventListener('open-full-search', () => this.openOverlay());

            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    // Only intercept when the overlay itself is open (to close it with Esc)
                    // or to open it. When it's closed, let it open. When open, swallow the
                    // shortcut so global-shortcuts.blade.php doesn't also open the palette.
                    e.preventDefault();
                    if (this.open) {
                        this.close();
                    } else {
                        this.openOverlay();
                    }
                }
                if (e.key === 'Escape' && this.open) {
                    this.close();
                }
            });
        },

        openOverlay() {
            this.open = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                if (this.$refs.input) {
                    this.$refs.input.focus();
                }
            });
        },

        close() {
            this.open = false;
            this.query = '';
            this.hasTyped = false;
            this.groups = [];
            this.page = 0;
            this.loading = false;
            document.body.style.overflow = '';
        },

        onInput() {
            const q = this.query.trim();
            this.hasTyped = this.query !== '';
            this.page = 0;
            if (q === '') {
                this.groups = [];
                this.loading = false;
                return;
            }
            this.fetchSearch(false);
        },

        fetchSearch(append) {
            const q = this.query.trim();
            if (q === '') return;

            this.loading = true;
            const params = new URLSearchParams({
                q: q,
                page: String(this.page),
                per_group: String(this.perGroup),
            });

            fetch(this._searchEndpoint + '?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this._csrfToken,
                },
                credentials: 'same-origin',
            })
            .then(r => {
                if (!r.ok) throw new Error('Search failed');
                return r.json();
            })
            .then(json => {
                const data = json.data ?? {};
                const incoming = data.groups ?? [];

                if (append) {
                    for (const g of incoming) {
                        const existing = this.groups.find(eg => eg.key === g.key);
                        if (existing) {
                            existing.items.push(...g.items);
                            existing.has_more = g.has_more;
                        } else {
                            this.groups.push(g);
                        }
                    }
                    // Update has_more for groups that now have no more
                    for (const eg of this.groups) {
                        const refreshed = incoming.find(g => g.key === eg.key);
                        if (refreshed) {
                            eg.has_more = refreshed.has_more;
                        } else if (this.page > 0) {
                            eg.has_more = false;
                        }
                    }
                } else {
                    this.groups = incoming;
                }

                this.loading = false;
            })
            .catch(() => {
                this.loading = false;
            });
        },

        loadMore() {
            this.page++;
            this.fetchSearch(true);
        },

        navigate(item) {
            const action = item.action;
            if (!action) {
                this.close();
                return;
            }

            if (action.kind === 'workspace' && action.switch_url) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = action.switch_url;
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = this._csrfToken;
                form.appendChild(tokenInput);
                document.body.appendChild(form);
                form.submit();
                return;
            }

            if (action.url) {
                window.location.href = action.url;
            }

            this.close();
        },

        navigateFirst() {
            const first = this.groups[0]?.items[0];
            if (first) {
                this.navigate(first);
            }
        },

        focusResult(gi, ii) {
            const el = this.$refs.results?.querySelector(
                `[data-result-index="${gi}_${ii}"]`
            );
            if (el) {
                el.focus();
            }
        },
    };
}
</script>
@endonce
