@extends('user.layouts.app')

@section('title', 'Dialer')

@section('content')
<div class="max-w-5xl mx-auto" id="dialer-root"
     data-favorites-url="{{ route('user.dialer.favorites.store') }}"
     data-reorder-url="{{ route('user.dialer.favorites.reorder') }}"
     data-flag-url="{{ route('user.dialer.flag') }}"
     data-profile-url="{{ route('user.dialer.profile') }}">
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
    @if(!empty($frequent))
    <div class="card-premium p-5 mb-6">
        <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);"><i class="fas fa-fire mr-1.5" style="color:#fb7185;"></i> Frequently contacted</h3>
        <div class="flex gap-3 overflow-x-auto pb-1">
            @foreach($frequent as $fr)
                <a href="{{ route('user.dialer.profile', ['number' => $fr['number'], 'contact' => $fr['contact_id']]) }}"
                   class="flex flex-col items-center flex-shrink-0 w-20 text-center">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center text-xs font-bold text-white mb-1" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">{{ $fr['initials'] }}</div>
                    <div class="text-[11px] font-semibold truncate w-full" style="color:var(--text-primary);">{{ $fr['name'] }}</div>
                    <div class="text-[10px]" style="color:var(--text-faint);">{{ $fr['calls'] }} calls @if($fr['is_spam'])· <span style="color:#ef4444;">spam</span>@endif</div>
                </a>
            @endforeach
        </div>
    </div>
    @endif

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
                    <button type="button" onclick="dialerPress('{{ $key }}')" class="py-3 rounded-xl transition flex flex-col items-center" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-primary);" onmouseover="this.style.background='rgba(124,58,237,.12)'" onmouseout="this.style.background='rgba(255,255,255,.04)'">
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
                <button type="button" onclick="dialerProfile()" class="py-3 rounded-xl text-sm font-medium text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                    <i class="fas fa-id-card mr-1"></i> Profile
                </button>
            </div>
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
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">{{ $c->initials() }}</div>
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

            @if(!empty($recent))
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Recent</h3>
                <div class="space-y-2">
                    @foreach($recent as $r)
                        <a href="{{ route('user.dialer.profile', ['number' => $r['number'], 'contact' => $r['contact_id']]) }}"
                           class="flex items-center justify-between px-3 py-2 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold truncate flex items-center gap-1.5" style="color:var(--text-primary);">
                                    {{ $r['name'] }}
                                    @if($r['calls'] > 1)<span class="text-[10px] px-1.5 rounded-full" style="background:rgba(124,58,237,.15);color:#a78bfa;">×{{ $r['calls'] }}</span>@endif
                                    @if($r['biolink'])<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(236,72,153,.15);color:#f472b6;">Sayzio</span>@endif
                                    @if($r['is_spam'])<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(239,68,68,.15);color:#ef4444;">SPAM</span>@endif
                                    @if($r['is_blocked'])<span class="px-1 rounded text-[8px] font-bold" style="background:rgba(107,114,128,.2);color:#9ca3af;">BLOCKED</span>@endif
                                </div>
                                <div class="text-[11px] flex items-center gap-2" style="color:var(--text-faint);">
                                    <span>{{ $r['last_human'] }}</span>
                                    @if($r['tag'])<span class="px-1.5 rounded-full" style="background:rgba(255,255,255,.06);">{{ $r['tag'] }}</span>@endif
                                    @if($r['outcome'])<span>· {{ str_replace('_',' ',$r['outcome']) }}</span>@endif
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-[10px] opacity-40 ml-2"></i>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
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
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">${m.initials}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold truncate" style="color:var(--text-primary);">${escapeHtml(m.name)}${m.biolink ? ' <span class=\"px-1 rounded text-[8px] font-bold\" style=\"background:rgba(236,72,153,.15);color:#f472b6\">Sayzio</span>' : ''}${m.is_spam ? ' <span class=\"px-1 rounded text-[8px] font-bold\" style=\"background:rgba(239,68,68,.15);color:#ef4444\">SPAM</span>' : ''}</div>
                    <div class="text-[11px] truncate" style="color:var(--text-muted);">${m.phone || ''}</div>
                </div>
            </a>
        `).join('');
    } catch (e) { /* ignore */ }
}
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

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
</script>
@endsection
