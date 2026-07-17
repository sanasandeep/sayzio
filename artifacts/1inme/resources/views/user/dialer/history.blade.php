@extends('user.layouts.app')

@section('title', 'Call history')

@section('content')
<div class="max-w-3xl mx-auto"
     id="dialer-history-root"
     data-history-url="{{ route('user.dialer.history') }}"
     data-profile-url="{{ route('user.dialer.profile') }}"
     data-dialer-url="{{ route('user.dialer.index') }}"
     x-data="dialerHistory()"
     x-init="init()">

    @include('user.partials.page-hero', [
        'title'    => 'Call history',
        'subtitle' => 'Your full lookup and call log — search, filter, and act on every entry.',
        'icon'     => 'fa-history',
        'chips'    => [],
    ])

    {{-- Back link + clear-all button --}}
    <div class="flex items-center justify-between mb-5">
        <a href="{{ route('user.dialer.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium" style="color:var(--text-muted);">
            <i class="fas fa-arrow-left text-xs"></i> Back to dialer
        </a>
        <button type="button" @click="confirmClear()" class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.18);" x-show="items.length > 0">
            <i class="fas fa-trash text-[10px]"></i> Clear history
        </button>
    </div>

    {{-- Filter bar --}}
    <div class="card-premium p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Text search --}}
            <div class="relative sm:col-span-1">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                <input type="text" x-model.debounce.400ms="filters.q" @input="reload()"
                       placeholder="Name, number, note…"
                       class="w-full pl-8 pr-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
            </div>

            {{-- Outcome filter --}}
            <div class="relative">
                <select x-model="filters.outcome" @change="reload()"
                        class="w-full appearance-none px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    <option value="">All outcomes</option>
                    <option value="called">Called</option>
                    <option value="messaged">Messaged</option>
                    <option value="no_answer">No answer</option>
                    <option value="voicemail">Voicemail</option>
                    <option value="busy">Busy</option>
                    <option value="wrong_number">Wrong number</option>
                    <option value="completed">Completed</option>
                </select>
                <i class="fas fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[10px] pointer-events-none" style="color:var(--text-faint);"></i>
            </div>

            {{-- Tag filter --}}
            <div class="relative">
                <i class="fas fa-tag absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                <input type="text" x-model.debounce.400ms="filters.tag" @input="reload()"
                       placeholder="Filter by tag…"
                       class="w-full pl-8 pr-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
            </div>
        </div>

        {{-- Active filter chips + clear --}}
        <div class="flex flex-wrap items-center gap-2 mt-3" x-show="filters.q || filters.outcome || filters.tag">
            <span class="text-[11px]" style="color:var(--text-faint);">Filtering:</span>
            <span x-show="filters.q" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px]" style="background:rgba(61,107,255,.15);color:#90acff;">
                <i class="fas fa-search text-[9px]"></i> <span x-text="filters.q"></span>
                <button type="button" @click="filters.q=''; reload()" class="ml-0.5 opacity-60 hover:opacity-100"><i class="fas fa-times text-[9px]"></i></button>
            </span>
            <span x-show="filters.outcome" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px]" style="background:rgba(61,107,255,.15);color:#90acff;">
                <span x-text="filters.outcome.replace(/_/g,' ')"></span>
                <button type="button" @click="filters.outcome=''; reload()" class="ml-0.5 opacity-60 hover:opacity-100"><i class="fas fa-times text-[9px]"></i></button>
            </span>
            <span x-show="filters.tag" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px]" style="background:rgba(61,107,255,.15);color:#90acff;">
                <i class="fas fa-tag text-[9px]"></i> <span x-text="filters.tag"></span>
                <button type="button" @click="filters.tag=''; reload()" class="ml-0.5 opacity-60 hover:opacity-100"><i class="fas fa-times text-[9px]"></i></button>
            </span>
            <button type="button" @click="clearFilters()" class="text-[11px] font-medium" style="color:var(--text-muted);">Clear all</button>
        </div>
    </div>

    {{-- Loading state --}}
    <div x-show="loading && items.length === 0" class="flex items-center justify-center py-16">
        <div class="w-8 h-8 rounded-full border-2 animate-spin" style="border-color:rgba(255,255,255,.10);border-top-color:#3d6bff;"></div>
    </div>

    {{-- Empty state --}}
    <div x-show="!loading && items.length === 0" class="text-center py-16">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(255,255,255,.05);">
            <i class="fas fa-history text-xl" style="color:var(--text-faint);"></i>
        </div>
        <p class="text-sm font-medium" style="color:var(--text-primary);">No history yet</p>
        <p class="text-xs mt-1" style="color:var(--text-muted);">
            <span x-show="!filters.q && !filters.outcome && !filters.tag">Lookups and calls will appear here once you start using the dialer.</span>
            <span x-show="filters.q || filters.outcome || filters.tag">No entries match your filters.</span>
        </p>
    </div>

    {{-- Day-grouped list --}}
    <div class="space-y-6" x-show="items.length > 0">
        <template x-for="group in groupedItems" :key="group.date">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="text-[11px] font-semibold uppercase tracking-wider" style="color:var(--text-faint);" x-text="group.label"></span>
                    <div class="flex-1 h-px" style="background:rgba(255,255,255,.06);"></div>
                </div>
                <div class="space-y-2">
                    <template x-for="item in group.items" :key="item.id">
                        <div class="flex items-start gap-3 px-4 py-3 rounded-xl group/row" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                            {{-- Avatar --}}
                            <a :href="profileHref(item)" class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);" x-text="item.initials"></a>

                            {{-- Identity + meta --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <a :href="profileHref(item)" class="text-sm font-semibold" style="color:var(--text-primary);" x-text="item.name"></a>
                                    <span x-show="item.outcome" class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold" :style="outcomeBadgeStyle(item.outcome)" x-text="item.outcome ? item.outcome.replace(/_/g,' ') : ''"></span>
                                    <span x-show="item.tag" class="px-1.5 py-0.5 rounded-full text-[10px]" style="background:rgba(255,255,255,.07);color:var(--text-muted);" x-text="item.tag"></span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 text-[11px]" style="color:var(--text-faint);">
                                    <span x-text="item.number_e164 || ''"></span>
                                    <span x-show="item.note" class="truncate max-w-[180px]" x-text="item.note ? '· ' + item.note : ''"></span>
                                    <span class="ml-auto flex-shrink-0" x-text="item.at_human"></span>
                                </div>

                                {{-- Inline channel quick-actions (call back + other channels) --}}
                                <div class="flex items-center gap-1 mt-2 flex-wrap" x-show="item.number_e164">
                                    <template x-for="ch in enabledChannels" :key="ch.key">
                                        <button type="button" @click="chanOpen(ch.js, item.number_e164)"
                                                :title="ch.label"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-[10px]"
                                                :style="`background:${ch.color}24;color:${ch.color};`">
                                            <i :class="ch.fa"></i>
                                        </button>
                                    </template>

                                    {{-- Edit outcome/note --}}
                                    <button type="button" @click="openEdit(item)"
                                            class="w-7 h-7 rounded-full flex items-center justify-center text-[10px]"
                                            style="background:rgba(255,255,255,.06);color:var(--text-muted);">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Delete button --}}
                            <button type="button" @click="deleteEntry(item)"
                                    class="flex-shrink-0 w-7 h-7 rounded-full items-center justify-center text-[10px] hidden group-hover/row:flex"
                                    style="background:rgba(239,68,68,.10);color:#ef4444;"
                                    title="Delete entry">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Load more --}}
        <div class="flex justify-center pt-2" x-show="hasMore">
            <button type="button" @click="loadMore()"
                    :disabled="loading"
                    class="px-5 py-2 rounded-xl text-sm font-medium"
                    style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                <span x-show="!loading">Load more</span>
                <span x-show="loading"><i class="fas fa-circle-notch fa-spin mr-1"></i> Loading…</span>
            </button>
        </div>

        {{-- Result count --}}
        <p class="text-center text-[11px] pb-2" style="color:var(--text-faint);" x-show="!hasMore && total > 0">
            <span x-text="total"></span> <span x-text="total === 1 ? 'entry' : 'entries'"></span> total
        </p>
    </div>

    {{-- Edit entry modal --}}
    <div x-show="editItem" class="fixed inset-0 z-[60] flex items-center justify-center p-4" x-cloak>
        <div class="absolute inset-0" style="background:rgba(0,0,0,.55);" @click="editItem=null"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-5 space-y-4" style="background:var(--surface-soft);border:1px solid var(--border-glass);">
            <h3 class="text-base font-bold" style="color:var(--text-primary);">Edit log entry</h3>
            <p class="text-xs -mt-1" style="color:var(--text-muted);" x-show="editItem" x-text="editItem?.name"></p>

            {{-- Outcome --}}
            <div>
                <label class="text-xs font-semibold block mb-1.5" style="color:var(--text-muted);">Outcome</label>
                <select x-model="editFields.outcome"
                        class="w-full appearance-none px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    <option value="">— none —</option>
                    <option value="called">Called</option>
                    <option value="messaged">Messaged</option>
                    <option value="no_answer">No answer</option>
                    <option value="voicemail">Voicemail</option>
                    <option value="busy">Busy</option>
                    <option value="wrong_number">Wrong number</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            {{-- Note --}}
            <div>
                <label class="text-xs font-semibold block mb-1.5" style="color:var(--text-muted);">Note</label>
                <textarea x-model="editFields.note" rows="3" placeholder="Add a note…"
                          class="w-full px-3 py-2 rounded-xl text-sm resize-none" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);"></textarea>
            </div>

            {{-- Tag --}}
            <div>
                <label class="text-xs font-semibold block mb-1.5" style="color:var(--text-muted);">Tag</label>
                <input type="text" x-model="editFields.tag" placeholder="e.g. lead, follow-up…" maxlength="50"
                       class="w-full px-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" @click="editItem=null" class="px-4 py-2 rounded-xl text-sm font-medium" style="background:var(--bg-glass-hover);color:var(--text-primary);border:1px solid var(--border-glass);">Cancel</button>
                <button type="button" @click="saveEdit()" :disabled="editSaving" class="px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    <span x-show="!editSaving">Save</span>
                    <span x-show="editSaving"><i class="fas fa-circle-notch fa-spin mr-1"></i> Saving…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const HISTORY_ROOT = document.getElementById('dialer-history-root');
const HISTORY_URL  = HISTORY_ROOT.dataset.historyUrl;
const PROFILE_URL  = HISTORY_ROOT.dataset.profileUrl;

const DIALER_CH_CATALOG = @json($channelCatalog);
const DIALER_CH_ENABLED = @json(array_values($channelEnabled));

function digitsOf(v) { return (v || '').replace(/[^0-9]/g, ''); }
function chanUrl(mode, v) {
    v = (v || '').trim();
    const d = digitsOf(v);
    switch (mode) {
        case 'tel':    return v ? 'tel:' + v : '';
        case 'sms':    return v ? 'sms:' + v : '';
        case 'wa':     return d ? 'https://wa.me/' + d : '';
        case 'tg':     return d ? 'https://t.me/+' + d : '';
        case 'signal': return d ? 'https://signal.me/#p/+' + d : '';
        case 'viber':  return d ? 'viber://chat?number=%2B' + d : '';
        default:       return '';
    }
}
function chanOpen(mode, v) {
    const url = chanUrl(mode, v);
    if (!url) return;
    if (mode === 'tel' || mode === 'sms' || mode === 'viber') window.location.href = url;
    else window.open(url, '_blank');
}

const OUTCOME_COLORS = {
    called:        { bg: 'rgba(34,197,94,.15)',  fg: '#22c55e' },
    messaged:      { bg: 'rgba(59,130,246,.15)', fg: '#60a5fa' },
    completed:     { bg: 'rgba(34,197,94,.15)',  fg: '#22c55e' },
    no_answer:     { bg: 'rgba(251,191,36,.15)', fg: '#fbbf24' },
    voicemail:     { bg: 'rgba(168,85,247,.15)', fg: '#c084fc' },
    busy:          { bg: 'rgba(251,191,36,.15)', fg: '#fbbf24' },
    wrong_number:  { bg: 'rgba(239,68,68,.15)',  fg: '#ef4444' },
};

function dialerHistory() {
    return {
        items:       [],
        total:       0,
        page:        0,
        hasMore:     false,
        loading:     false,
        filters:     { q: '', outcome: '', tag: '' },

        enabledChannels: DIALER_CH_CATALOG.filter(c => DIALER_CH_ENABLED.includes(c.key)),

        editItem:   null,
        editFields: { outcome: '', note: '', tag: '' },
        editSaving: false,

        get groupedItems() {
            const groups = {};
            const now = new Date();
            for (const item of this.items) {
                const d = item.at_date || 'unknown';
                if (!groups[d]) {
                    groups[d] = { date: d, label: this.dayLabel(item.at, now), items: [] };
                }
                groups[d].items.push(item);
            }
            return Object.values(groups);
        },

        dayLabel(iso, now) {
            if (!iso) return 'Unknown date';
            const d = new Date(iso);
            if (isNaN(d.getTime())) return iso;
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const day   = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            const diff  = Math.round((today - day) / 86400000);
            if (diff === 0) return 'Today';
            if (diff === 1) return 'Yesterday';
            if (diff < 7)   return d.toLocaleDateString(undefined, { weekday: 'long' });
            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: diff > 365 ? 'numeric' : undefined });
        },

        outcomeBadgeStyle(outcome) {
            const c = OUTCOME_COLORS[outcome] || { bg: 'rgba(255,255,255,.08)', fg: 'var(--text-muted)' };
            return `background:${c.bg};color:${c.fg};`;
        },

        profileHref(item) {
            let u = PROFILE_URL + '?number=' + encodeURIComponent(item.number_e164 || '');
            if (item.contact_id) u += '&contact=' + item.contact_id;
            return u;
        },

        async init() {
            await this.load(0, false);
        },

        async reload() {
            this.page  = 0;
            this.items = [];
            await this.load(0, false);
        },

        async loadMore() {
            if (!this.hasMore || this.loading) return;
            await this.load(this.page + 1, true);
        },

        async load(page, append) {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page });
                if (this.filters.q)       params.set('q', this.filters.q);
                if (this.filters.outcome) params.set('outcome', this.filters.outcome);
                if (this.filters.tag)     params.set('tag', this.filters.tag);

                const r = await fetch(HISTORY_URL + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!r.ok) throw new Error('fetch failed');
                const body = await r.json();
                const data = body.data || {};

                this.total   = data.total   || 0;
                this.hasMore = data.has_more || false;
                this.page    = page;

                if (append) {
                    this.items = this.items.concat(data.items || []);
                } else {
                    this.items = data.items || [];
                }
            } catch (e) {
                // Swallow; items stays as-is.
            } finally {
                this.loading = false;
            }
        },

        clearFilters() {
            this.filters = { q: '', outcome: '', tag: '' };
            this.reload();
        },

        async deleteEntry(item) {
            if (!confirm(`Delete this entry for "${item.name}"?`)) return;
            try {
                const r = await fetch(HISTORY_URL + '/' + item.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!r.ok) throw new Error('delete failed');
                this.items = this.items.filter(i => i.id !== item.id);
                this.total = Math.max(0, this.total - 1);
            } catch (e) {
                alert('Could not delete entry. Please try again.');
            }
        },

        async confirmClear() {
            const msg = (this.filters.outcome || this.filters.tag || this.filters.q)
                ? 'Clear all entries matching the current filters?'
                : 'Clear your entire call history? This cannot be undone.';
            if (!confirm(msg)) return;
            try {
                const params = new URLSearchParams();
                if (this.filters.outcome) params.set('outcome', this.filters.outcome);
                if (this.filters.tag)     params.set('tag', this.filters.tag);
                const r = await fetch(HISTORY_URL + '?' + params.toString(), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!r.ok) throw new Error('clear failed');
                await this.reload();
            } catch (e) {
                alert('Could not clear history. Please try again.');
            }
        },

        openEdit(item) {
            this.editItem   = item;
            this.editFields = { outcome: item.outcome || '', note: item.note || '', tag: item.tag || '' };
        },

        async saveEdit() {
            if (!this.editItem) return;
            this.editSaving = true;
            try {
                const r = await fetch(HISTORY_URL + '/' + this.editItem.id, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.editFields),
                });
                if (!r.ok) throw new Error('save failed');
                const body = await r.json();
                const updated = body.data?.log || {};
                const idx = this.items.findIndex(i => i.id === this.editItem.id);
                if (idx !== -1) {
                    this.items[idx] = {
                        ...this.items[idx],
                        outcome: updated.outcome ?? null,
                        note:    updated.note    ?? null,
                        tag:     updated.tag     ?? null,
                    };
                }
                this.editItem = null;
            } catch (e) {
                alert('Could not save changes. Please try again.');
            } finally {
                this.editSaving = false;
            }
        },
    };
}
</script>
@endsection
