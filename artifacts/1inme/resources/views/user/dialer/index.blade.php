@extends('user.layouts.app')

@section('title', 'Dialer')

@section('content')
<div class="max-w-5xl mx-auto" id="dialer-root"
     data-favorites-url="{{ route('user.dialer.favorites.store') }}"
     data-reorder-url="{{ route('user.dialer.favorites.reorder') }}"
     data-flag-url="{{ route('user.dialer.flag') }}"
     data-profile-url="{{ route('user.dialer.profile') }}"
     data-live-url="{{ route('user.dialer.live') }}"
     data-live-cursor="{{ $liveCursor ?? '' }}"
     data-speed-dial-assign-url="{{ route('user.dialer.speed-dial.assign') }}"
     data-speed-dial-unassign-url="{{ route('user.dialer.speed-dial.unassign') }}">
    @include('user.partials.page-hero', [
        'title' => 'Dialer',
        'subtitle' => 'Speed-dial favorites, smart recents and T9 search, call, text, email or share your Link in Bio in one tap.',
        'icon' => 'fa-phone',
        'chips' => [],
    ])

    {{-- Speed dial / favorites --}}
    <div class="card-premium p-5 mb-6" id="favorites-card" @if(empty($favorites)) style="display:none" @endif>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold" style="color:var(--text-primary);"><i class="fas fa-star mr-1.5" style="color:#fbbf24;"></i> Speed dial</h3>
            <div class="flex items-center gap-2">
                <button type="button" onclick="openSpeedDialManager()" class="text-[11px] font-medium inline-flex items-center gap-1" style="color:var(--text-muted);">
                    <i class="fas fa-hashtag text-[10px]"></i> Digit slots
                </button>
                <span class="text-[11px]" style="color:var(--text-faint);">Drag to reorder · Long-press key to speed-dial</span>
            </div>
        </div>
        <div id="favorites-grid" class="grid grid-cols-3 sm:grid-cols-5 gap-3">
            @foreach($favorites as $f)
                @include('user.dialer._favorite', ['f' => $f])
            @endforeach
        </div>
    </div>

    {{-- Frequently contacted --}}
    <div class="card-premium p-5 mb-6" id="frequent-card" @if(empty($frequent)) style="display:none" @endif>
        <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);"><i class="fas fa-fire mr-1.5" style="color:#fb7185;"></i> Frequently contacted</h3>
        <div class="flex gap-3 overflow-x-auto pb-1" id="frequent-strip">
            @foreach($frequent as $fr)
                @include('user.dialer._frequent', ['fr' => $fr])
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Number pad --}}
        <div class="card-premium p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold" style="color:var(--text-primary);">Number pad <span class="text-[10px] font-normal" style="color:var(--text-faint);"> - search everything</span></h3>
                {{-- Keypad mode toggle: T9 digit grid ↔ alphanumeric keyboard.
                     Both modes write to the same input and feed the same
                     universal search, so results never differ by mode. --}}
                <div class="inline-flex rounded-lg overflow-hidden" style="border:1px solid rgba(255,255,255,.12);">
                    <button type="button" id="mode-t9" onclick="setKeypadMode('t9')" class="px-2.5 py-1 text-[11px] font-semibold">T9</button>
                    <button type="button" id="mode-abc" onclick="setKeypadMode('abc')" class="px-2.5 py-1 text-[11px] font-semibold">Keyboard</button>
                </div>
            </div>
            <input id="dialer-number" type="text" inputmode="tel" value="" placeholder="+1 555 0100 or a name" autocomplete="off"
                   class="w-full text-center text-2xl font-mono px-3 py-3 rounded-xl mb-4" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">

            {{-- Live universal results (grouped) --}}
            <div id="dialer-live" class="mb-3 hidden space-y-3 max-h-72 overflow-y-auto"></div>

            <div id="keypad-grid" class="grid grid-cols-3 gap-2 mb-4">
                @php $sub = ['1'=>'','2'=>'ABC','3'=>'DEF','4'=>'GHI','5'=>'JKL','6'=>'MNO','7'=>'PQRS','8'=>'TUV','9'=>'WXYZ','*'=>'','0'=>'+','#'=>'']; @endphp
                @foreach(['1','2','3','4','5','6','7','8','9','*','0','#'] as $key)
                    @php $isDigit = ctype_digit($key) && $key !== '0'; @endphp
                    <button type="button" id="key-btn-{{ $key }}"
                            onclick="dialerPress('{{ $key }}')"
                            @if($isDigit)
                            onmousedown="keyLongPressStart(event, '{{ $key }}')"
                            onmouseup="keyLongPressCancel()"
                            onmouseleave="keyLongPressCancel()"
                            @endif
                            class="py-3 rounded-xl transition flex flex-col items-center relative select-none" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-primary);" onmouseover="this.style.background='rgba(61,107,255,.12)'" onmouseout="this.style.background='rgba(255,255,255,.04)'">
                        <span class="text-lg font-semibold">{{ $key }}</span>
                        @if($sub[$key])<span class="text-[9px] tracking-wider" style="color:var(--text-faint);">{{ $sub[$key] }}</span>@endif
                        @if($isDigit)<span id="key-hint-{{ $key }}" class="text-[8px] font-bold leading-none mt-0.5 hidden" style="color:#90acff;"></span>@endif
                    </button>
                @endforeach
            </div>
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="dialerBack()" class="py-3 rounded-xl text-sm font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                    <i class="fas fa-backspace"></i>
                </button>
                <button type="button" onclick="dialerCall()" class="py-3 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#22c55e,#10b981);">
                    <i class="fas fa-phone mr-1"></i> Call
                </button>
                <button type="button" onclick="dialerProfile()" class="py-3 rounded-xl text-sm font-medium text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    <i class="fas fa-id-card mr-1"></i> Profile
                </button>
            </div>
            {{-- Direct channel actions on the typed number — one tap to reach
                 anyone by their preferred app, no Google needed. Only the
                 channels the user picked are shown (customisable below). --}}
            <div class="flex items-center justify-between mt-3 mb-1">
                <span class="text-[10px] font-semibold uppercase tracking-wide" style="color:var(--text-faint);">Quick channels</span>
                <button type="button" onclick="openChannelPicker()" class="text-[11px] font-medium inline-flex items-center gap-1" style="color:var(--text-muted);">
                    <i class="fas fa-sliders-h text-[10px]"></i> Customize
                </button>
            </div>
            <div id="keypad-channels" class="grid grid-cols-4 gap-2"></div>
        </div>

        {{-- Search + recent --}}
        <div class="space-y-4">
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Search everything <span class="text-[10px] font-normal" style="color:var(--text-faint);"> - contacts, people, links, workspaces</span></h3>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                    <input type="text" id="universal-q" value="{{ $q }}" placeholder="Name, handle, alias, keyword…"
                           class="w-full pl-9 pr-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                </div>

                {{-- Advanced filter chips (contacts + verification) --}}
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <button type="button" id="filter-verified" onclick="toggleFilter('verified')" class="uni-chip px-2.5 py-1 rounded-full text-[11px] font-semibold" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-muted);">
                        <i class="fas fa-badge-check mr-1"></i> Verified
                    </button>
                    <button type="button" id="filter-biolink" onclick="toggleFilter('has_biolink')" class="uni-chip px-2.5 py-1 rounded-full text-[11px] font-semibold" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-muted);">
                        <i class="fas fa-link mr-1"></i> On Sayzio
                    </button>
                    <div class="relative">
                        <input type="text" id="filter-tag" oninput="runUniversal()" placeholder="Tag…"
                               class="w-24 px-2.5 py-1 rounded-full text-[11px]" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    </div>
                </div>

                <div id="search-results" class="mt-3 space-y-3"></div>

                {{-- Scan-a-card nudge: shown when search has no results --}}
                <div id="dialer-scan-nudge" class="mt-3 hidden">
                    <a href="{{ route('user.contacts.scan.create', ['from' => 'dialer']) }}"
                       class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl transition text-left w-full"
                       style="background:rgba(236,72,153,.08);border:1px solid rgba(236,72,153,.20);color:var(--text-primary);">
                        <i class="fas fa-camera text-pink-400 text-sm"></i>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold leading-tight" style="color:var(--text-primary);">Scan a business card</p>
                            <p class="text-[11px] leading-tight mt-0.5" style="color:var(--text-muted);">Use AI to extract contact details from a photo</p>
                        </div>
                        <span class="ml-auto text-[10px] font-bold uppercase tracking-wide shrink-0" style="color:#ec4899;">AI</span>
                    </a>
                </div>
            </div>

            <div class="card-premium p-5" id="recent-card" @if(empty($recent)) style="display:none" @endif>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold" style="color:var(--text-primary);">Recent</h3>
                    <a href="{{ route('user.dialer.history') }}" class="text-[11px] font-medium inline-flex items-center gap-1" style="color:var(--text-muted);">
                        <i class="fas fa-history text-[10px]"></i> View all history
                    </a>
                </div>
                <div class="space-y-2" id="recent-list">
                    @foreach($recent as $r)
                        @include('user.dialer._recent', ['r' => $r])
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const dialerRoot = document.getElementById('dialer-root');
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const inp     = document.getElementById('dialer-number');
const liveBox = document.getElementById('dialer-live');
const searchInp = document.getElementById('universal-q');
const SEARCH_URL       = '{{ route('user.dialer.search') }}';
const SUGGESTIONS_URL  = '{{ route('user.dialer.suggestions') }}';

// Advanced-search filter state (verification badge / on-Sayzio / tag).
const uniFilters = { verified: false, has_biolink: false, tag: '' };

function dialerPress(k) { inp.value += k; liveFilter(); }
function dialerBack()   { inp.value = inp.value.slice(0, -1); liveFilter(); }
function dialerCall()   {
    const v = inp.value.trim();
    if (!v) return;
    window.location.href = 'tel:' + v;
}
function dialerProfile() {
    const v = inp.value.trim();
    if (!v) return;
    window.location.href = dialerRoot.dataset.profileUrl + '?number=' + encodeURIComponent(v);
}

// ── Direct channel actions ───────────────────────────────────────────
// Config-independent: these are plain device/deep-link handoffs (tel/sms/
// wa.me/t.me/signal.me/viber) that need no Google Contacts or any integration.
// The catalog + the user's preferred (enabled) channels are the single source
// of truth shared with PHP (App\Modules\User\Support\DialerChannels), so the
// keypad, favourites, frequent and recents rows never drift.
const DIALER_CH_CATALOG = @json($channelCatalog);
let   DIALER_CH_ENABLED = @json(array_values($channelEnabled));
const DIALER_CH_URL = '{{ route('user.dialer.channels') }}';

function digitsOf(v) { return (v || '').replace(/[^0-9]/g, ''); }
// Build the deep-link for a channel `js` mode + typed value.
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
function chanMeta(key) { return DIALER_CH_CATALOG.find(c => c.key === key); }

// Buttons for the currently-typed number under the keypad.
function renderKeypadChannels() {
    const box = document.getElementById('keypad-channels');
    if (!box) return;
    box.innerHTML = DIALER_CH_ENABLED.map(key => {
        const c = chanMeta(key);
        if (!c) return '';
        return `<button type="button" onclick="chanOpen('${c.js}', inp.value)" title="${c.label}" class="py-2.5 rounded-xl text-xs font-medium flex flex-col items-center gap-0.5" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-primary);"><i class="${c.fa}" style="color:${c.color};"></i> ${c.short}</button>`;
    }).join('');
}

// Shared direct-action cluster (mirrors user/dialer/_channel_actions.blade.php)
// so favourites/frequent/recents rendered live match the server-rendered rows.
function channelActions(num, size) {
    num = (num || '').trim();
    if (!num) return '';
    const sm = size === 'sm';
    const btn = sm ? 'w-7 h-7' : 'w-8 h-8';
    const ico = sm ? 'text-[10px]' : 'text-xs';
    const n = num.replace(/'/g, "\\'");
    const buttons = DIALER_CH_ENABLED.map(key => {
        const c = chanMeta(key);
        if (!c) return '';
        return `<button type="button" onclick="chanOpen('${c.js}','${n}')" title="${c.label}" class="${btn} rounded-full flex items-center justify-center" style="background:${c.color}24;color:${c.color};"><i class="${c.fa} ${ico}"></i></button>`;
    }).join('');
    return `<div class="flex items-center justify-center flex-wrap gap-1">${buttons}</div>`;
}

// ── Channel picker (per-user preferred channels) ─────────────────────
function openChannelPicker() {
    document.getElementById('channel-picker')?.remove();
    const rows = DIALER_CH_CATALOG.map(c => {
        const on = DIALER_CH_ENABLED.includes(c.key);
        return `<label class="flex items-center gap-3 px-3 py-2.5 rounded-xl cursor-pointer" style="background:var(--bg-glass-hover);border:1px solid var(--border-glass);">
            <input type="checkbox" value="${c.key}" ${on ? 'checked' : ''} class="channel-pick-cb" style="accent-color:${c.color};width:16px;height:16px;">
            <span class="w-7 h-7 rounded-full flex items-center justify-center" style="background:${c.color}24;color:${c.color};"><i class="${c.fa} text-xs"></i></span>
            <span class="text-sm font-medium" style="color:var(--text-primary);">${c.short}</span>
        </label>`;
    }).join('');
    const el = document.createElement('div');
    el.id = 'channel-picker';
    el.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4';
    el.innerHTML = `
        <div class="absolute inset-0" style="background:rgba(0,0,0,.55);" onclick="document.getElementById('channel-picker').remove()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-5" style="background:var(--surface-soft);border:1px solid var(--border-glass);">
            <h3 class="text-base font-bold mb-1" style="color:var(--text-primary);">Preferred channels</h3>
            <p class="text-xs mb-4" style="color:var(--text-muted);">Pick the messaging apps you actually use. Only these appear as one-tap actions on the keypad, favourites and recents.</p>
            <div class="space-y-2 max-h-[50vh] overflow-y-auto">${rows}</div>
            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" onclick="document.getElementById('channel-picker').remove()" class="px-4 py-2 rounded-xl text-sm font-medium" style="background:var(--bg-glass-hover);color:var(--text-primary);border:1px solid var(--border-glass);">Cancel</button>
                <button type="button" onclick="saveChannelPicker(this)" class="px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">Save</button>
            </div>
        </div>`;
    document.body.appendChild(el);
}

async function saveChannelPicker(btn) {
    const picked = Array.from(document.querySelectorAll('.channel-pick-cb'))
        .filter(cb => cb.checked).map(cb => cb.value);
    btn.disabled = true;
    try {
        const res = await fetch(DIALER_CH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ channels: picked }),
        });
        const json = await res.json();
        if (!res.ok) throw new Error('save failed');
        DIALER_CH_ENABLED = (json.data && json.data.enabled) || DIALER_CH_ENABLED;
        document.getElementById('channel-picker')?.remove();
        renderKeypadChannels();
        // Re-pull the live lists so favourites/frequent/recents re-render their
        // channel rows with the new selection (full state when cursor is reset).
        liveCursor = null; pollLive();
    } catch (e) {
        btn.disabled = false;
        alert('Could not save channels. Please try again.');
    }
}

function profileHref(number, contactId) {
    let u = dialerRoot.dataset.profileUrl + '?number=' + encodeURIComponent(number || '');
    if (contactId) u += '&contact=' + encodeURIComponent(contactId);
    return u;
}

function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

// ── Universal finder ─────────────────────────────────────────────────
// One grouped search across Contacts, People, My links, Followed and
// Workspaces (server: App\Modules\User\Support\DialerSearch). Fed by BOTH
// keypad modes (T9 grid + alphanumeric keyboard) and the advanced box, so
// the result set never differs by how you typed.
async function fetchUniversal(q, filters) {
    const params = new URLSearchParams();
    if (q) params.set('q', q);
    if (filters) {
        if (filters.verified)    params.set('filter', 'verified');
        if (filters.has_biolink) params.set('has_biolink', '1');
        if (filters.tag)         params.set('tag', filters.tag);
    }
    try {
        const r = await fetch(SEARCH_URL + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!r.ok) return null;
        const body = await r.json();
        return body.data || null;
    } catch (e) { return null; }
}

function uniItemHtml(item) {
    const badges = [];
    if (item.badge)   badges.push(`<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(236,72,153,.15);color:#f472b6">${escapeHtml(item.badge)}</span>`);
    if (item.verified) badges.push(`<span title="${escapeHtml(item.verified_label || 'Verified')}" style="color:#3d6bff"><i class="fas fa-check-circle text-[10px]"></i></span>`);
    const typeLabel = item.type_label
        ? `<span class="px-1 rounded text-[8px] font-bold flex-shrink-0" style="background:rgba(255,255,255,.08);color:var(--text-faint)">${escapeHtml(item.type_label)}</span>` : '';
    const inner = `
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">${escapeHtml(item.initials)}</div>
        <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold truncate flex items-center gap-1" style="color:var(--text-primary);">${escapeHtml(item.title)} ${badges.join(' ')}</div>
            <div class="text-[11px] truncate" style="color:var(--text-muted);">${escapeHtml(item.subtitle || '')}</div>
        </div>
        ${typeLabel}`;
    const cls = 'flex items-center gap-2 px-2 py-1.5 rounded-lg';
    const style = 'background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);';
    const a = item.action || {};
    let row;
    if (a.kind === 'workspace' && a.switch_url) {
        row = `<button type="button" onclick="switchWorkspace('${a.switch_url}')" class="${cls} w-full text-left" style="${style}">${inner}</button>`;
    } else if (a.url) {
        row = `<a href="${a.url}" class="${cls}" style="${style}">${inner}</a>`;
    } else {
        row = `<div class="${cls}" style="${style}">${inner}</div>`;
    }
    // Direct app-connection links (call / SMS / WhatsApp / Telegram / …) for
    // any result that carries a phone number — one tap to reach them without
    // leaving search. Rendered as a sibling so we never nest buttons in an <a>.
    const channels = a.number ? `<div class="mt-1 px-2 pb-0.5">${channelActions(a.number, 'sm')}</div>` : '';
    return channels ? `<div>${row}${channels}</div>` : row;
}

function renderGroups(container, data, emptyMsg) {
    if (!data || !Array.isArray(data.groups) || !data.groups.length) {
        container.innerHTML = `<div class="text-xs text-center py-2" style="color:var(--text-faint);">${emptyMsg}</div>`;
        return;
    }
    container.innerHTML = data.groups.map(g => `
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wide mb-1.5 flex items-center gap-1.5" style="color:var(--text-faint);">
                ${escapeHtml(g.label)} <span style="opacity:.6">${g.items.length}</span>
            </div>
            <div class="space-y-1.5">${g.items.map(uniItemHtml).join('')}</div>
        </div>
    `).join('');
}

async function switchWorkspace(url) {
    try {
        await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
    } catch (e) { /* ignore */ }
    window.location.reload();
}

// Keypad live results (both T9 grid + keyboard mode write to #dialer-number).
let _t = null;
function liveFilter() {
    const q = inp.value.trim();
    clearTimeout(_t);
    if (!q) { liveBox.classList.add('hidden'); liveBox.innerHTML = ''; return; }
    _t = setTimeout(async () => {
        const data = await fetchUniversal(q, null);
        liveBox.classList.remove('hidden');
        renderGroups(liveBox, data, 'No matches');
    }, 200);
}

// Load and display pre-query suggestions when the search box is empty.
async function loadSuggestions(box) {
    try {
        const r = await fetch(SUGGESTIONS_URL, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!r.ok) { box.innerHTML = ''; return; }
        const body = await r.json();
        const data = body.data || null;
        if (!data || !data.total) { box.innerHTML = ''; return; }
        renderGroups(box, data, '');
    } catch (e) { box.innerHTML = ''; }
}

// Re-fetch suggestions after a relevant mutation (favorite added/removed,
// new follower/subscriber via the live poll) — but only while the empty
// state is actually showing them (no query, no filter chips), so we never
// stomp on live search results or fetch for a hidden box.
function refreshSuggestions() {
    const box = document.getElementById('search-results');
    if (!box) return;
    const q = (searchInp?.value || '').trim();
    const anyFilter = uniFilters.verified || uniFilters.has_biolink || uniFilters.tag;
    if (!q && !anyFilter) void loadSuggestions(box);
}

// Advanced search box + filter chips.
let _tu = null;
function runUniversal() {
    uniFilters.tag = (document.getElementById('filter-tag')?.value || '').trim();
    const q = (searchInp?.value || '').trim();
    const box = document.getElementById('search-results');
    if (!box) return;
    clearTimeout(_tu);
    const anyFilter = uniFilters.verified || uniFilters.has_biolink || uniFilters.tag;
    if (!q && !anyFilter) { void loadSuggestions(box); return; }
    _tu = setTimeout(async () => {
        const data = await fetchUniversal(q, uniFilters);
        renderGroups(box, data, 'No matches');
        const nudge = document.getElementById('dialer-scan-nudge');
        if (nudge) nudge.classList.toggle('hidden', !!(data && data.total > 0));
    }, 220);
}

function toggleFilter(key) {
    uniFilters[key] = !uniFilters[key];
    const el = document.getElementById(key === 'verified' ? 'filter-verified' : 'filter-biolink');
    if (el) {
        if (uniFilters[key]) { el.style.background = 'rgba(61,107,255,.18)'; el.style.color = '#90acff'; el.style.borderColor = 'rgba(61,107,255,.4)'; }
        else { el.style.background = 'rgba(255,255,255,.05)'; el.style.color = 'var(--text-muted)'; el.style.borderColor = 'rgba(255,255,255,.10)'; }
    }
    runUniversal();
}

// Keypad mode toggle: T9 digit grid ↔ alphanumeric keyboard.
let keypadMode = 't9';
function setKeypadMode(mode) {
    keypadMode = mode;
    const grid = document.getElementById('keypad-grid');
    const t9btn = document.getElementById('mode-t9');
    const abcbtn = document.getElementById('mode-abc');
    const active = 'background:rgba(61,107,255,.2);color:#90acff;';
    const idle   = 'background:transparent;color:var(--text-muted);';
    if (mode === 't9') {
        if (grid) grid.style.display = '';
        inp.setAttribute('inputmode', 'tel');
        if (t9btn)  t9btn.style.cssText = active;
        if (abcbtn) abcbtn.style.cssText = idle;
    } else {
        if (grid) grid.style.display = 'none';
        inp.setAttribute('inputmode', 'text');
        if (abcbtn) abcbtn.style.cssText = active;
        if (t9btn)  t9btn.style.cssText = idle;
        inp.focus();
    }
}

renderKeypadChannels();
setKeypadMode('t9');
inp.addEventListener('input', liveFilter);
if (searchInp) {
    searchInp.addEventListener('input', runUniversal);
    if (searchInp.value.trim()) runUniversal();
}

// ── Speed-dial digit management ───────────────────────────────────────
const SPEED_DIAL_ASSIGN_URL   = dialerRoot.dataset.speedDialAssignUrl;
const SPEED_DIAL_UNASSIGN_URL = dialerRoot.dataset.speedDialUnassignUrl;

// Build a map of digit → favorite from the current favorites grid.
function buildSpeedDialMap() {
    const map = {};
    if (!favGrid) return map;
    Array.from(favGrid.children).forEach(el => {
        const digit = el.dataset.speedDigit;
        if (digit) map[parseInt(digit, 10)] = { id: parseInt(el.dataset.favId, 10), label: el.querySelector('.text-\\[11px\\]')?.textContent?.trim() || '' };
    });
    return map;
}

// Refresh the subtle digit hints under keys 1–9 on the keypad.
function refreshKeypadDigitHints() {
    const map = buildSpeedDialMap();
    for (let d = 1; d <= 9; d++) {
        const hint = document.getElementById('key-hint-' + d);
        if (!hint) continue;
        const fav = map[d];
        if (fav) {
            const initials = document.querySelector('#fav-' + fav.id + ' .text-sm.font-bold')?.textContent?.trim()
                || document.querySelector('#fav-' + fav.id + ' .text-\\[11px\\].font-semibold')?.textContent?.trim()
                || '•';
            hint.textContent = initials.slice(0, 3);
            hint.classList.remove('hidden');
        } else {
            hint.textContent = '';
            hint.classList.add('hidden');
        }
    }
}

// Long-press on a digit key (1–9): reveal the speed-dial for that slot, or
// let the user assign/unassign a favorite. Uses a 600ms threshold to
// distinguish a tap (append digit) from a hold (open speed dial).
let _longPressTimer = null;
let _longPressDidFire = false;
function keyLongPressStart(ev, digit) {
    _longPressDidFire = false;
    clearTimeout(_longPressTimer);
    _longPressTimer = setTimeout(() => {
        _longPressDidFire = true;
        ev.preventDefault();
        // Prevent the click from appending the digit after the hold fires.
        const btn = document.getElementById('key-btn-' + digit);
        if (btn) {
            const origOnclick = btn.onclick;
            btn.onclick = null;
            setTimeout(() => { btn.onclick = origOnclick; }, 200);
        }
        const map = buildSpeedDialMap();
        const existing = map[parseInt(digit, 10)];
        if (existing) {
            // A favorite already owns this digit — open its profile or let the user clear it.
            openSpeedDialLaunch(digit, existing);
        } else {
            // Digit is free — offer to assign from the favorites list.
            openSpeedDialPicker(null, null, null, parseInt(digit, 10));
        }
    }, 600);
}
function keyLongPressCancel() {
    clearTimeout(_longPressTimer);
    _longPressTimer = null;
}

// Open the quick-launch sheet for an already-assigned key.
function openSpeedDialLaunch(digit, fav) {
    document.getElementById('speed-dial-launch')?.remove();
    const el = document.createElement('div');
    el.id = 'speed-dial-launch';
    el.className = 'fixed inset-0 z-[60] flex items-end sm:items-center justify-center p-4';
    el.innerHTML = `
        <div class="absolute inset-0" style="background:rgba(0,0,0,.55);" onclick="document.getElementById('speed-dial-launch').remove()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-5" style="background:var(--surface-soft);border:1px solid var(--border-glass);">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-base font-bold" style="background:rgba(61,107,255,.85);color:#fff;">${escapeHtml(String(digit))}</div>
                <div>
                    <div class="text-sm font-bold" style="color:var(--text-primary);">${escapeHtml(fav.label || 'Speed-dial ' + digit)}</div>
                    <div class="text-xs" style="color:var(--text-muted);">Speed-dial key ${escapeHtml(String(digit))}</div>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" onclick="document.getElementById('speed-dial-launch').remove(); window.location.href=profileHref(document.querySelector('#fav-${fav.id} a')?.href?.split('number=')[1]?.split('&')[0] ? decodeURIComponent(document.querySelector('#fav-${fav.id} a')?.href?.split('number=')[1]?.split('&')[0]) : '',null);" class="py-2.5 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    <i class="fas fa-id-card mr-1"></i> Open profile
                </button>
                <button type="button" onclick="document.getElementById('speed-dial-launch').remove(); openSpeedDialPicker(null, ${fav.id}, ${digit});" class="py-2.5 rounded-xl text-sm font-medium" style="background:var(--bg-glass-hover);color:var(--text-primary);border:1px solid var(--border-glass);">
                    <i class="fas fa-exchange-alt mr-1"></i> Change
                </button>
            </div>
            <button type="button" onclick="clearSpeedDial(${digit}, this)" class="mt-2 w-full py-2 rounded-xl text-sm font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20);">
                <i class="fas fa-times mr-1"></i> Clear key ${escapeHtml(String(digit))}
            </button>
        </div>`;
    document.body.appendChild(el);
}

// Open the picker to assign a favorite to a speed-dial digit.
// Can be called from the bubble's # button or from a long-press on the key.
function openSpeedDialPicker(ev, favId, currentDigit, forDigit) {
    if (ev) { ev.preventDefault(); ev.stopPropagation(); }
    document.getElementById('speed-dial-picker')?.remove();
    const map = buildSpeedDialMap();
    const favItems = Array.from(favGrid?.children || []).map(el => ({
        id:    parseInt(el.dataset.favId, 10),
        label: el.querySelector('.text-\\[11px\\].font-semibold')?.textContent?.trim() || 'Favorite',
        digit: el.dataset.speedDigit ? parseInt(el.dataset.speedDigit, 10) : null,
    }));
    const forStr = forDigit ? ` for key <strong>${forDigit}</strong>` : '';
    const digits = [1,2,3,4,5,6,7,8,9];
    const digitRows = digits.map(d => {
        const owner = map[d];
        const isCurrent = currentDigit && d === parseInt(currentDigit, 10);
        const ownerLabel = owner ? `<span class="ml-1 text-[10px] font-bold truncate max-w-[80px]" style="color:#90acff;">${escapeHtml(owner.label)}</span>` : '';
        return `<button type="button" onclick="doAssignDigit(${favId || 'null'}, ${d}, this)" class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm transition" style="background:${isCurrent ? 'rgba(61,107,255,.2)' : 'var(--bg-glass-hover)'};border:1px solid ${isCurrent ? 'rgba(61,107,255,.5)' : 'var(--border-glass)'};color:var(--text-primary);">
            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background:rgba(61,107,255,.3);color:#90acff;">${d}</span>
            ${ownerLabel || '<span class="text-xs" style="color:var(--text-faint);">free</span>'}
        </button>`;
    }).join('');

    const favSelect = forDigit ? `
        <div class="mb-3">
            <label class="text-[11px] font-semibold uppercase tracking-wide mb-1.5 block" style="color:var(--text-faint);">Assign favorite</label>
            <select id="speed-dial-fav-select" class="w-full px-3 py-2 rounded-xl text-sm" style="background:var(--bg-glass-hover);border:1px solid var(--border-glass);color:var(--text-primary);">
                ${favItems.map(f => `<option value="${f.id}" ${f.digit ? '' : ''}>${escapeHtml(f.label)}${f.digit ? ' (key '+f.digit+')' : ''}</option>`).join('')}
            </select>
        </div>` : '';

    const el = document.createElement('div');
    el.id = 'speed-dial-picker';
    el.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4';
    el.innerHTML = `
        <div class="absolute inset-0" style="background:rgba(0,0,0,.55);" onclick="document.getElementById('speed-dial-picker').remove()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-5" style="background:var(--surface-soft);border:1px solid var(--border-glass);">
            <h3 class="text-base font-bold mb-1" style="color:var(--text-primary);">Speed-dial digit</h3>
            <p class="text-xs mb-4" style="color:var(--text-muted);">Pick a key${forStr}. Long-pressing that digit on the keypad will open this contact instantly.</p>
            ${favSelect}
            <div class="grid grid-cols-3 gap-2 mb-4">${digitRows}</div>
            ${currentDigit ? `<button type="button" onclick="clearSpeedDial(${currentDigit}, this)" class="w-full py-2 rounded-xl text-sm font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20);"><i class="fas fa-times mr-1"></i> Remove key ${currentDigit}</button>` : ''}
            <button type="button" onclick="document.getElementById('speed-dial-picker').remove()" class="mt-2 w-full py-1.5 rounded-xl text-xs font-medium" style="background:var(--bg-glass-hover);color:var(--text-muted);border:1px solid var(--border-glass);">Cancel</button>
        </div>`;
    document.body.appendChild(el);
}

async function doAssignDigit(favId, digit, btn) {
    const selectEl = document.getElementById('speed-dial-fav-select');
    const resolvedFavId = favId || (selectEl ? parseInt(selectEl.value, 10) : null);
    if (!resolvedFavId) { alert('Select a favorite first.'); return; }
    btn.disabled = true;
    try {
        const res = await fetch(SPEED_DIAL_ASSIGN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ favorite_id: resolvedFavId, digit }),
        });
        if (!res.ok) throw new Error('assign failed');
        document.getElementById('speed-dial-picker')?.remove();
        // Force a full live refresh so all digit badges update.
        liveCursor = null; pollLive();
    } catch (e) {
        btn.disabled = false;
        alert('Could not assign digit. Please try again.');
    }
}

async function clearSpeedDial(digit, btn) {
    btn.disabled = true;
    try {
        const res = await fetch(SPEED_DIAL_UNASSIGN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ digit }),
        });
        if (!res.ok) throw new Error('unassign failed');
        document.getElementById('speed-dial-launch')?.remove();
        document.getElementById('speed-dial-picker')?.remove();
        liveCursor = null; pollLive();
    } catch (e) {
        btn.disabled = false;
        alert('Could not clear digit. Please try again.');
    }
}

// Management overlay showing all 9 digit slots and their current owners.
function openSpeedDialManager() {
    document.getElementById('speed-dial-manager')?.remove();
    const map = buildSpeedDialMap();
    const rows = [1,2,3,4,5,6,7,8,9].map(d => {
        const fav = map[d];
        return `<div class="flex items-center gap-3 px-3 py-2.5 rounded-xl" style="background:var(--bg-glass-hover);border:1px solid var(--border-glass);">
            <div class="w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0" style="background:rgba(61,107,255,.3);color:#90acff;">${d}</div>
            <div class="flex-1 min-w-0">
                ${fav
                    ? `<div class="text-sm font-semibold truncate" style="color:var(--text-primary);">${escapeHtml(fav.label)}</div>`
                    : `<div class="text-sm" style="color:var(--text-faint);"> - unassigned</div>`
                }
            </div>
            ${fav
                ? `<div class="flex gap-1">
                    <button type="button" onclick="openSpeedDialPicker(null,${fav.id},${d},null)" class="px-2 py-1 rounded-lg text-[11px] font-medium" style="background:rgba(61,107,255,.15);color:#90acff;border:1px solid rgba(61,107,255,.25);">Change</button>
                    <button type="button" onclick="clearSpeedDial(${d}, this)" class="px-2 py-1 rounded-lg text-[11px] font-medium" style="background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.25);">Clear</button>
                   </div>`
                : `<button type="button" onclick="document.getElementById('speed-dial-manager').remove(); openSpeedDialPicker(null,null,null,${d})" class="px-2 py-1 rounded-lg text-[11px] font-medium" style="background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid var(--border-glass);">Assign</button>`
            }
        </div>`;
    }).join('');

    const el = document.createElement('div');
    el.id = 'speed-dial-manager';
    el.className = 'fixed inset-0 z-[60] flex items-center justify-center p-4';
    el.innerHTML = `
        <div class="absolute inset-0" style="background:rgba(0,0,0,.55);" onclick="document.getElementById('speed-dial-manager').remove()"></div>
        <div class="relative w-full max-w-sm rounded-2xl p-5" style="background:var(--surface-soft);border:1px solid var(--border-glass);">
            <h3 class="text-base font-bold mb-1" style="color:var(--text-primary);">Speed-dial key slots</h3>
            <p class="text-xs mb-4" style="color:var(--text-muted);">Long-press any key 1–9 on the keypad to instantly reach the assigned favorite.</p>
            <div class="space-y-2 max-h-[60vh] overflow-y-auto">${rows}</div>
            <button type="button" onclick="document.getElementById('speed-dial-manager').remove()" class="mt-4 w-full py-1.5 rounded-xl text-xs font-medium" style="background:var(--bg-glass-hover);color:var(--text-muted);border:1px solid var(--border-glass);">Close</button>
        </div>`;
    document.body.appendChild(el);
}

// ── Favorites: remove + drag-to-reorder ──────────────────────────────
const favGrid = document.getElementById('favorites-grid');
const favCard = document.getElementById('favorites-card');

async function removeFavorite(ev, id) {
    ev.preventDefault(); ev.stopPropagation();
    try {
        const r = await fetch('{{ url('user/dialer/favorites') }}/' + id, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
        });
        if (r.ok) {
            document.getElementById('fav-' + id)?.remove();
            if (favGrid && favGrid.children.length === 0) favCard.style.display = 'none';
            refreshSuggestions();
        }
    } catch (e) {}
}

let dragId = null;
function favDragStart(ev, id) { dragId = id; ev.dataTransfer.effectAllowed = 'move'; }
function favDragOver(ev) { ev.preventDefault(); }
function favDrop(ev, overId) {
    ev.preventDefault();
    if (dragId === null || dragId === overId) return;
    const dragEl = document.getElementById('fav-' + dragId);
    const overEl = document.getElementById('fav-' + overId);
    if (!dragEl || !overEl) return;
    const rect = overEl.getBoundingClientRect();
    const after = ev.clientX > rect.left + rect.width / 2;
    favGrid.insertBefore(dragEl, after ? overEl.nextSibling : overEl);
    persistFavOrder();
    dragId = null;
}
async function persistFavOrder() {
    const order = Array.from(favGrid.children).map(el => parseInt(el.dataset.favId, 10)).filter(Boolean);
    try {
        await fetch(dialerRoot.dataset.reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ order }),
        });
    } catch (e) {}
}

// ── Near-real-time cross-device sync (poll a lastId-style cursor) ─────
// No sockets: we poll /dialer/live with our last cursor. When another
// device changes favorites / flags / the call log, the cursor advances and
// the fresh lists come back, so this page re-renders within a few seconds.
function renderFav(f) {
    const digitBadge = f.speed_dial_digit
        ? `<div class="absolute -top-1 -left-1 w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold z-10 pointer-events-none" style="background:rgba(61,107,255,.85);color:#fff;border:1.5px solid rgba(61,107,255,.3);">${f.speed_dial_digit}</div>`
        : '';
    return `<div id="fav-${f.id}" data-fav-id="${f.id}" data-speed-digit="${f.speed_dial_digit || ''}" draggable="true"
         ondragstart="favDragStart(event, ${f.id})" ondragover="favDragOver(event)" ondrop="favDrop(event, ${f.id})"
         class="relative group flex flex-col items-center text-center cursor-move">
        ${digitBadge}
        <a href="${profileHref(f.number, f.contact_id)}" class="flex flex-col items-center w-full">
            <div class="w-14 h-14 rounded-full flex items-center justify-center text-sm font-bold text-white mb-1" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">${escapeHtml(f.initials)}</div>
            <div class="text-[11px] font-semibold truncate w-full" style="color:var(--text-primary);">${escapeHtml(f.label)}</div>
            ${f.biolink ? '<span class="text-[8px] font-bold" style="color:#f472b6;">Sayzio</span>' : ''}
        </a>
        ${f.number ? `<div class="mt-1 w-full">${channelActions(f.number, 'sm')}</div>` : ''}
        <div class="absolute -top-1 -right-1 flex gap-0.5 opacity-0 group-hover:opacity-100 transition">
            <button type="button" onclick="openSpeedDialPicker(event,${f.id},${f.speed_dial_digit || 'null'},null)" title="Assign speed-dial digit"
                    class="w-5 h-5 rounded-full text-[10px] flex items-center justify-center"
                    style="background:rgba(61,107,255,.85);color:#fff;"><i class="fas fa-hashtag"></i></button>
            <button type="button" onclick="removeFavorite(event, ${f.id})" title="Remove favorite"
                    class="w-5 h-5 rounded-full text-[10px] flex items-center justify-center"
                    style="background:rgba(239,68,68,.9);color:#fff;"><i class="fas fa-times"></i></button>
        </div>
    </div>`;
}

function renderFrequent(fr) {
    const num = fr.number || '';
    return `<div class="flex flex-col items-center flex-shrink-0 w-20 text-center">
        <a href="${profileHref(num, fr.contact_id)}" class="flex flex-col items-center w-full">
            <div class="w-12 h-12 rounded-full flex items-center justify-center text-xs font-bold text-white mb-1" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">${escapeHtml(fr.initials)}</div>
            <div class="text-[11px] font-semibold truncate w-full" style="color:var(--text-primary);">${escapeHtml(fr.name)}</div>
            <div class="text-[10px]" style="color:var(--text-faint);">${fr.calls} calls${fr.is_spam ? ' · <span style=\"color:#ef4444;\">spam</span>' : ''}</div>
        </a>
        ${num ? `<div class="mt-1 w-full">${channelActions(num, 'sm')}</div>` : ''}
    </div>`;
}

function renderRecent(r) {
    const num = r.number || '';
    const badges = [
        (r.calls > 1) ? `<span class="text-[10px] px-1.5 rounded-full" style="background:rgba(61,107,255,.15);color:#90acff;">×${r.calls}</span>` : '',
        r.biolink   ? '<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(236,72,153,.15);color:#f472b6;">Sayzio</span>' : '',
        r.is_spam   ? '<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(239,68,68,.15);color:#ef4444;">SPAM</span>' : '',
        r.is_blocked? '<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(107,114,128,.2);color:#9ca3af;">BLOCKED</span>' : '',
    ].join('');
    const meta = [
        `<span>${escapeHtml(r.last_human || '')}</span>`,
        r.tag     ? `<span class="px-1.5 rounded-full" style="background:rgba(255,255,255,.06);">${escapeHtml(r.tag)}</span>` : '',
        r.outcome ? `<span>· ${escapeHtml((r.outcome || '').replace(/_/g,' '))}</span>` : '',
    ].join(' ');
    return `<div class="flex items-center gap-2 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
        <a href="${profileHref(num, r.contact_id)}" class="min-w-0 flex-1">
            <div class="text-sm font-semibold truncate flex items-center gap-1.5" style="color:var(--text-primary);">${escapeHtml(r.name)} ${badges}</div>
            <div class="text-[11px] flex items-center gap-2" style="color:var(--text-faint);">${meta}</div>
        </a>
        ${num ? `<div class="flex-shrink-0">${channelActions(num, 'md')}</div>` : ''}
    </div>`;
}

function applyLive(d) {
    if (favGrid && Array.isArray(d.favorites)) {
        favGrid.innerHTML = d.favorites.map(renderFav).join('');
        if (favCard) favCard.style.display = d.favorites.length ? '' : 'none';
        refreshKeypadDigitHints();
    }
    const freqStrip = document.getElementById('frequent-strip');
    const freqCard  = document.getElementById('frequent-card');
    if (freqStrip && Array.isArray(d.frequent)) {
        freqStrip.innerHTML = d.frequent.map(renderFrequent).join('');
        if (freqCard) freqCard.style.display = d.frequent.length ? '' : 'none';
    }
    const recList = document.getElementById('recent-list');
    const recCard = document.getElementById('recent-card');
    if (recList && Array.isArray(d.recents)) {
        recList.innerHTML = d.recents.map(renderRecent).join('');
        if (recCard) recCard.style.display = d.recents.length ? '' : 'none';
    }
}

// Seed the cursor from the server-rendered snapshot so the first poll only
// re-renders when another device actually changed something since page load
// (changed=false → no churn). If the seed is missing for any reason, the first
// changed=true response still applies, so the lists can never stay stale.
let liveCursor = dialerRoot.dataset.liveCursor || null;
async function pollLive() {
    try {
        const url = dialerRoot.dataset.liveUrl + (liveCursor ? '?since=' + encodeURIComponent(liveCursor) : '');
        const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) return;
        const body = await r.json();
        const d = body.data || {};
        liveCursor = d.cursor || liveCursor;
        if (d.changed) { applyLive(d); refreshSuggestions(); }
    } catch (e) { /* ignore */ }
}
pollLive();
setInterval(pollLive, 12000);

// Seed suggestions for the search empty state on page load.
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('search-results');
    if (box) void loadSuggestions(box);
    // Seed key-hint labels from the server-rendered favorites grid.
    refreshKeypadDigitHints();
});
</script>
@endsection
