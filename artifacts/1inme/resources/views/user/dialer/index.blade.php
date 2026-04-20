@extends('user.layouts.app')

@section('title', 'Dialer')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Dialer',
        'subtitle' => 'Type a number or search a contact, then call or email — and see if they have a 1INME biolink.',
        'icon' => 'fa-phone',
        'chips' => [],
    ])

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Number pad --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Number pad</h3>
            <input id="dialer-number" type="tel" value="" placeholder="+1 555 0100" autocomplete="off"
                   class="w-full text-center text-2xl font-mono px-3 py-3 rounded-xl mb-4" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">

            {{-- Live filter results: matches your number/name as you type --}}
            <div id="dialer-live" class="mb-3 hidden space-y-2 max-h-48 overflow-y-auto"></div>

            <div class="grid grid-cols-3 gap-2 mb-4">
                @foreach(['1','2','3','4','5','6','7','8','9','*','0','#'] as $key)
                    <button type="button" onclick="dialerPress('{{ $key }}')" class="py-4 rounded-xl text-lg font-semibold transition" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-primary);" onmouseover="this.style.background='rgba(124,58,237,.12)'" onmouseout="this.style.background='rgba(255,255,255,.04)'">{{ $key }}</button>
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
                                        @if($c->biolink_user_id)<span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">1INME</span>@endif
                                    </div>
                                    <div class="text-xs truncate" style="color:var(--text-muted);">{{ $first?->value ?? '—' }}</div>
                                </div>
                                <i class="fas fa-chevron-right text-[10px] opacity-40"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            @if($recent->isNotEmpty())
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary);">Recent</h3>
                <div class="space-y-2">
                    @foreach($recent as $r)
                        <a href="{{ route('user.dialer.profile', ['number' => $r->number_e164, 'contact' => $r->contact_id]) }}"
                           class="flex items-center justify-between px-3 py-2 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);">
                            <div class="min-w-0">
                                <div class="text-sm font-mono truncate" style="color:var(--text-primary);">{{ $r->contact?->nameForDisplay() ?? $r->number_e164 }}</div>
                                <div class="text-[11px]" style="color:var(--text-faint);">{{ $r->looked_up_at->diffForHumans() }}</div>
                            </div>
                            <i class="fas fa-chevron-right text-[10px] opacity-40"></i>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
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
    window.location.href = '{{ route('user.dialer.profile') }}?number=' + encodeURIComponent(v);
}

// Debounced live filter — hits the JSON branch of the index endpoint and
// renders matching contacts under the keypad as the user dials/searches.
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
                    <div class="text-xs font-semibold truncate" style="color:var(--text-primary);">${m.name}${m.biolink ? ' <span class=\"px-1 rounded text-[8px] font-bold\" style=\"background:rgba(236,72,153,.15);color:#f472b6\">1INME</span>' : ''}</div>
                    <div class="text-[11px] truncate" style="color:var(--text-muted);">${m.phone || ''}</div>
                </div>
            </a>
        `).join('');
    } catch (e) { /* ignore */ }
}
inp.addEventListener('input', liveFilter);
if (searchInp) {
    searchInp.addEventListener('input', () => {
        // mirror search box query into the live box (one source of truth)
        inp.value = searchInp.value;
        liveFilter();
    });
}
</script>
@endsection
