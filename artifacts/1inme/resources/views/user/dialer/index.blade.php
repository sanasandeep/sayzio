@extends('user.layouts.app')

@section('title', 'Dialer')

@section('content')
<div class="max-w-5xl mx-auto" id="dialer-root"
     data-favorites-url="{{ route('user.dialer.favorites.store') }}"
     data-reorder-url="{{ route('user.dialer.favorites.reorder') }}"
     data-flag-url="{{ route('user.dialer.flag') }}"
     data-profile-url="{{ route('user.dialer.profile') }}"
     data-live-url="{{ route('user.dialer.live') }}"
     data-live-cursor="{{ $liveCursor ?? '' }}">
    @include('user.partials.page-hero', [
        'title' => 'Dialer',
        'subtitle' => 'Speed-dial favorites, smart recents and T9 search — call, text, email or share your Link in Bio in one tap.',
        'icon' => 'fa-phone',
        'chips' => [],
    ])

    {{-- Speed dial / favorites --}}
    <div class="card-premium p-5 mb-6" id="favorites-card" @if(empty($favorites)) style="display:none" @endif>
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold" style="color:var(--text-primary);"><i class="fas fa-star mr-1.5" style="color:#fbbf24;"></i> Speed dial</h3>
            <span class="text-[11px]" style="color:var(--text-faint);">Drag to reorder</span>
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
            <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Number pad <span class="text-[10px] font-normal" style="color:var(--text-faint);">— type digits to T9-search names too</span></h3>
            <input id="dialer-number" type="tel" value="" placeholder="+1 555 0100" autocomplete="off"
                   class="w-full text-center text-2xl font-mono px-3 py-3 rounded-xl mb-4" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">

            {{-- Live filter results --}}
            <div id="dialer-live" class="mb-3 hidden space-y-2 max-h-48 overflow-y-auto"></div>

            <div class="grid grid-cols-3 gap-2 mb-4">
                @php $sub = ['1'=>'','2'=>'ABC','3'=>'DEF','4'=>'GHI','5'=>'JKL','6'=>'MNO','7'=>'PQRS','8'=>'TUV','9'=>'WXYZ','*'=>'','0'=>'+','#'=>'']; @endphp
                @foreach(['1','2','3','4','5','6','7','8','9','*','0','#'] as $key)
                    <button type="button" onclick="dialerPress('{{ $key }}')" class="py-3 rounded-xl transition flex flex-col items-center" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-primary);" onmouseover="this.style.background='rgba(61,107,255,.12)'" onmouseout="this.style.background='rgba(255,255,255,.04)'">
                        <span class="text-lg font-semibold">{{ $key }}</span>
                        @if($sub[$key])<span class="text-[9px] tracking-wider" style="color:var(--text-faint);">{{ $sub[$key] }}</span>@endif
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
                <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Search contacts</h3>
                <form method="GET" action="{{ route('user.dialer.index') }}">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                        <input type="text" name="q" value="{{ $q }}" placeholder="Name or phone"
                               class="w-full pl-9 pr-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                    </div>
                </form>

                @if($q !== '' && $contacts->isEmpty())
                    <p class="text-xs mt-3" style="color:var(--text-muted);">No matches.</p>
                @elseif($contacts->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        @foreach($contacts as $c)
                            @php $first = $c->phones->first(); @endphp
                            <a href="{{ $first ? route('user.dialer.profile', ['number' => $first->value_e164 ?: $first->value, 'contact' => $c->id]) : route('user.contacts.show', $c) }}"
                               class="flex items-center gap-3 px-3 py-2 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">{{ $c->initials() }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">
                                        {{ $c->nameForDisplay() }}
                                        @if($c->biolink_user_id)<span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">Sayzio</span>@endif
                                    </div>
                                    <div class="text-xs truncate" style="color:var(--text-muted);">{{ $first?->value ?? '—' }}</div>
                                </div>
                                <i class="fas fa-chevron-right text-[10px] opacity-40"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="card-premium p-5" id="recent-card" @if(empty($recent)) style="display:none" @endif>
                <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Recent</h3>
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
const searchInp = document.querySelector('input[name="q"]');

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
        return `<label class="flex items-center gap-3 px-3 py-2.5 rounded-xl cursor-pointer" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);">
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
        <div class="relative w-full max-w-sm rounded-2xl p-5" style="background:var(--surface,#141019);border:1px solid rgba(255,255,255,.12);">
            <h3 class="text-base font-bold mb-1" style="color:var(--text-primary);">Preferred channels</h3>
            <p class="text-xs mb-4" style="color:var(--text-muted);">Pick the messaging apps you actually use. Only these appear as one-tap actions on the keypad, favourites and recents.</p>
            <div class="space-y-2 max-h-[50vh] overflow-y-auto">${rows}</div>
            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" onclick="document.getElementById('channel-picker').remove()" class="px-4 py-2 rounded-xl text-sm font-medium" style="background:rgba(255,255,255,.06);color:var(--text-primary);">Cancel</button>
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

// Debounced live filter — hits the JSON branch (T9-aware on the server).
let _t = null;
function liveFilter() {
    const q = inp.value.trim();
    clearTimeout(_t);
    if (!q) { liveBox.classList.add('hidden'); liveBox.innerHTML = ''; return; }
    _t = setTimeout(() => fetchMatches(q), 180);
}
async function fetchMatches(q) {
    try {
        const r = await fetch('{{ route('user.dialer.index') }}?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!r.ok) return;
        const data = await r.json();
        if (!data.matches.length) {
            liveBox.classList.remove('hidden');
            liveBox.innerHTML = '<div class="text-xs text-center py-2" style="color:var(--text-faint);">No matches</div>';
            return;
        }
        liveBox.classList.remove('hidden');
        liveBox.innerHTML = data.matches.map(m => `
            <a href="${m.profile_url}" class="flex items-center gap-2 px-2 py-1.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">${m.initials}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold truncate" style="color:var(--text-primary);">${escapeHtml(m.name)}${m.biolink ? ' <span class=\"px-1 rounded text-[8px] font-bold\" style=\"background:rgba(236,72,153,.15);color:#f472b6\">Sayzio</span>' : ''}${m.is_spam ? ' <span class=\"px-1 rounded text-[8px] font-bold\" style=\"background:rgba(239,68,68,.15);color:#ef4444\">SPAM</span>' : ''}</div>
                    <div class="text-[11px] truncate" style="color:var(--text-muted);">${m.phone || ''}</div>
                </div>
            </a>
        `).join('');
    } catch (e) { /* ignore */ }
}
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

renderKeypadChannels();
inp.addEventListener('input', liveFilter);
if (searchInp) {
    searchInp.addEventListener('input', () => {
        inp.value = searchInp.value;
        liveFilter();
    });
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
    return `<div id="fav-${f.id}" data-fav-id="${f.id}" draggable="true"
         ondragstart="favDragStart(event, ${f.id})" ondragover="favDragOver(event)" ondrop="favDrop(event, ${f.id})"
         class="relative group flex flex-col items-center text-center cursor-move">
        <a href="${profileHref(f.number, f.contact_id)}" class="flex flex-col items-center w-full">
            <div class="w-14 h-14 rounded-full flex items-center justify-center text-sm font-bold text-white mb-1" style="background:linear-gradient(135deg,#3d6bff,#ec4899);">${escapeHtml(f.initials)}</div>
            <div class="text-[11px] font-semibold truncate w-full" style="color:var(--text-primary);">${escapeHtml(f.label)}</div>
            ${f.biolink ? '<span class="text-[8px] font-bold" style="color:#f472b6;">Sayzio</span>' : ''}
        </a>
        ${f.number ? `<div class="mt-1 w-full">${channelActions(f.number, 'sm')}</div>` : ''}
        <button type="button" onclick="removeFavorite(event, ${f.id})" title="Remove favorite"
                class="absolute -top-1 -right-1 w-5 h-5 rounded-full text-[10px] opacity-0 group-hover:opacity-100 transition"
                style="background:rgba(239,68,68,.9);color:#fff;"><i class="fas fa-times"></i></button>
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
        if (d.changed) applyLive(d);
    } catch (e) { /* ignore */ }
}
pollLive();
setInterval(pollLive, 12000);
</script>
@endsection
