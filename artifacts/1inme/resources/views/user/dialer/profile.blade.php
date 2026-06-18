@extends('user.layouts.app')

@section('title', 'Dialer profile')

@section('content')
@php
    $e164 = $payload['number_e164'] ?? null;
    $bioUrl = $payload['biolink']['url'] ?? null;
    $displayName = $contact?->nameForDisplay() ?? ($matchedUser?->name ?? 'Unknown number');
    $myHandle = optional(auth()->user())->publicHandle();
    $myBioUrl = $myHandle ? url('/' . $myHandle) : null;
@endphp
<div class="max-w-3xl mx-auto" id="dialer-profile"
     data-flag-url="{{ route('user.dialer.flag') }}"
     data-fav-url="{{ route('user.dialer.favorites.store') }}"
     data-log-url="{{ route('user.dialer.log') }}"
     data-callback-url="{{ route('user.dialer.callback.set') }}"
     data-number="{{ $number }}"
     data-number-e164="{{ $e164 }}"
     data-contact-id="{{ $contact?->id }}">
    <a href="{{ route('user.dialer.index') }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back to dialer
    </a>

    <div class="card-premium p-6 mb-4">
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white flex-shrink-0" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                @if($contact && $contact->photoUrl())
                    <img src="{{ $contact->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                @elseif($contact)
                    {{ $contact->initials() }}
                @else
                    <i class="fas fa-user"></i>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold flex items-center gap-2 flex-wrap" style="color:var(--text-primary);">
                    {{ $displayName }}
                    <span id="badge-spam" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase @if(!$payload['is_spam']) hidden @endif" style="background:rgba(239,68,68,.15);color:#ef4444;">Spam</span>
                    <span id="badge-blocked" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase @if(!$payload['is_blocked']) hidden @endif" style="background:rgba(107,114,128,.2);color:#9ca3af;">Blocked</span>
                </h1>
                <div class="text-sm font-mono" style="color:var(--text-muted);">{{ $number }}</div>
                @if(!$contact && $matchedUser)
                    <p class="text-xs mt-1" style="color:#f472b6;"><i class="fas fa-id-badge mr-1"></i> Identified via 1INME biolink</p>
                @elseif($contact && $contact->organization)
                    <p class="text-xs mt-1" style="color:var(--text-muted);">{{ $contact->organization }}</p>
                @endif
            </div>
        </div>

        {{-- Consistent quick-action bar --}}
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mt-5">
            @if($number)
                <a href="tel:{{ $number }}" onclick="logQuick('called')" class="qa-btn" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                    <i class="fas fa-phone"></i><span>Call</span>
                </a>
                <a href="sms:{{ $number }}" onclick="logQuick('messaged')" class="qa-btn" style="background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.20)">
                    <i class="fas fa-comment-sms"></i><span>SMS</span>
                </a>
            @endif
            @if($contact && $contact->emails->isNotEmpty())
                <a href="mailto:{{ $contact->emails->first()->value }}" class="qa-btn" style="background:rgba(99,102,241,.12);color:#818cf8;border:1px solid rgba(99,102,241,.20)">
                    <i class="fas fa-envelope"></i><span>Email</span>
                </a>
            @endif
            <button type="button" onclick="shareMyBiolink()" class="qa-btn" style="background:rgba(236,72,153,.12);color:#f472b6;border:1px solid rgba(236,72,153,.20)">
                <i class="fas fa-share-nodes"></i><span>Share bio</span>
            </button>
            <button type="button" onclick="copyNumber()" class="qa-btn" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                <i class="fas fa-copy"></i><span>Copy</span>
            </button>
            @if($contact)
                <a href="{{ route('user.contacts.edit', $contact) }}" class="qa-btn" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                    <i class="fas fa-pen"></i><span>Edit</span>
                </a>
            @else
                <a href="{{ route('user.contacts.create', ['phone' => $number]) }}" class="qa-btn" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                    <i class="fas fa-user-plus"></i><span>Save</span>
                </a>
            @endif
        </div>

        {{-- Favorite + spam/block toggles --}}
        @if($e164)
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,.06);">
            <button type="button" id="fav-toggle" onclick="addFavorite()" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.20)">
                <i class="fas fa-star mr-1"></i> <span>{{ $isFavorite ? 'In speed dial' : 'Add to speed dial' }}</span>
            </button>
            <button type="button" id="spam-toggle" onclick="toggleFlag('is_spam')" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                <i class="fas fa-triangle-exclamation mr-1"></i> <span>{{ $payload['is_spam'] ? 'Unmark spam' : 'Mark spam' }}</span>
            </button>
            <button type="button" id="block-toggle" onclick="toggleFlag('is_blocked')" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(107,114,128,.15);color:#9ca3af;border:1px solid rgba(107,114,128,.25)">
                <i class="fas fa-ban mr-1"></i> <span>{{ $payload['is_blocked'] ? 'Unblock' : 'Block' }}</span>
            </button>
        </div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
            <i class="fas fa-circle-exclamation mr-1.5"></i> {{ session('error') }}
        </div>
    @endif

    @if($payload['biolink'])
        <div class="card-premium p-6" style="border:1px solid rgba(236,72,153,.30);">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:#f472b6;"><i class="fas fa-link mr-1"></i> 1INME biolink</div>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text-primary);">{{ $payload['biolink']['name'] }}</h2>
                    <p class="text-sm mb-4" style="color:var(--text-muted);">&commat;{{ $payload['biolink']['handle'] }}</p>
                    @if($payload['biolink']['url'])
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ $payload['biolink']['url'] }}" target="_blank" class="inline-block px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                                Open biolink <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                            @if($number)
                                <a href="sms:{{ $number }}?body={{ rawurlencode(($contact?->nameForDisplay() ? 'Hey ' . $contact->nameForDisplay() . ', ' : 'Hey, ') . "here's my 1INME page: " . $payload['biolink']['url']) }}"
                                   class="inline-block px-4 py-2 rounded-xl text-sm font-semibold"
                                   style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                                    <i class="fas fa-comment-sms mr-1"></i> Text biolink
                                </a>
                                @if($contact)
                                    <form method="POST" action="{{ route('user.contacts.biolink.sms', $contact) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="to" value="{{ $number }}">
                                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold"
                                                style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)"
                                                title="Send via your configured SMS gateway (desktop fallback)">
                                            <i class="fas fa-paper-plane mr-1"></i> Send via gateway
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @else
                        <p class="text-xs" style="color:var(--text-faint);">This user hasn't published a biolink yet.</p>
                    @endif
                </div>
                @if($contact)
                    <form method="POST" action="{{ route('user.contacts.biolink.detach', $contact) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Detach this biolink?', message: 'It will not auto-attach again on future syncs.', confirmText: 'Detach', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                        @csrf
                        <button class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                            Detach
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @else
        <div class="card-premium p-6 text-center">
            <i class="fas fa-circle-info text-2xl mb-2" style="color:var(--text-faint);"></i>
            <p class="text-sm mb-3" style="color:var(--text-muted);">No 1INME biolink found for this number.</p>
            @if($contact)
                <form method="POST" action="{{ route('user.contacts.biolink.attach', $contact) }}">
                    @csrf
                    <button class="text-xs font-medium" style="color:#a78bfa;">
                        <i class="fas fa-link mr-1"></i> Re-check / re-attach
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- Log a call (mini-CRM) --}}
    @if($e164)
    <div class="card-premium p-5 mt-4">
        <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Log this call</h3>
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach(['called'=>'Called','messaged'=>'Messaged','no_answer'=>'No answer','voicemail'=>'Voicemail','wrong_number'=>'Wrong number','completed'=>'Completed'] as $val=>$label)
                <button type="button" class="outcome-chip text-[11px] px-2.5 py-1 rounded-full" data-outcome="{{ $val }}"
                        style="background:rgba(255,255,255,.05);color:var(--text-muted);border:1px solid rgba(255,255,255,.08)">{{ $label }}</button>
            @endforeach
        </div>
        <input id="log-tag" type="text" placeholder="Tag (e.g. lead, family, vendor)" maxlength="50"
               class="w-full px-3 py-2 rounded-xl text-sm mb-2" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
        <textarea id="log-note" rows="2" placeholder="Note about this call…" maxlength="2000"
               class="w-full px-3 py-2 rounded-xl text-sm mb-2" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);"></textarea>
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <input id="cb-at" type="datetime-local" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                <button type="button" onclick="setCallback()" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)">
                    <i class="fas fa-bell mr-1"></i> Remind me
                </button>
            </div>
            <button type="button" onclick="saveLog()" class="text-xs px-4 py-2 rounded-xl font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">Save log</button>
        </div>
        @if($callback)
            <div class="mt-3 text-[11px] flex items-center gap-1.5" style="color:#a78bfa;">
                <i class="fas fa-bell"></i> Call-back reminder set for {{ \Illuminate\Support\Carbon::parse($callback['callback_at'])->diffForHumans() }}
            </div>
        @endif
    </div>
    @endif

    {{-- Recent activity --}}
    <div class="card-premium p-5 mt-4" id="activity-card" @if(empty($recent)) style="display:none" @endif>
        <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Recent activity</h3>
        <div class="space-y-2" id="activity-list">
            @foreach($recent as $r)
                <div class="py-1.5" style="border-top: 1px solid rgba(255,255,255,.06);">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-medium" style="color:var(--text-primary);">
                            {{ $r['outcome'] ? str_replace('_',' ',$r['outcome']) : 'Lookup' }}
                            @if($r['tag'])<span class="ml-1 px-1.5 rounded-full text-[10px]" style="background:rgba(255,255,255,.06);color:var(--text-muted);">{{ $r['tag'] }}</span>@endif
                        </div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $r['at_human'] }}</div>
                    </div>
                    @if($r['note'])<div class="text-[11px] mt-0.5" style="color:var(--text-muted);">{{ $r['note'] }}</div>@endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
.qa-btn { display:flex; flex-direction:column; align-items:center; gap:3px; padding:10px 4px; border-radius:12px; font-size:11px; font-weight:600; text-align:center; }
.qa-btn i { font-size:14px; }
.outcome-chip.active { background:rgba(124,58,237,.18) !important; color:#a78bfa !important; border-color:rgba(124,58,237,.35) !important; }
</style>

<script>
const dp = document.getElementById('dialer-profile');
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const NUM = dp.dataset.number || '';
const NUM_E164 = dp.dataset.numberE164 || '';
const CONTACT_ID = dp.dataset.contactId || null;
const MY_BIO_URL = @json($myBioUrl);
let selectedOutcome = null;

function toast(msg) {
    if (window.showToast) { window.showToast(msg); return; }
    console.log(msg);
}

async function post(url, body, method = 'POST') {
    const r = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
        body: body ? JSON.stringify(body) : undefined,
    });
    return { ok: r.ok, data: await r.json().catch(() => null) };
}

function copyNumber() {
    if (!NUM) return;
    navigator.clipboard?.writeText(NUM).then(() => toast('Number copied'));
}

function shareMyBiolink() {
    const url = MY_BIO_URL;
    if (!url) { toast('Publish a biolink first'); return; }
    if (navigator.share) { navigator.share({ url }).catch(() => {}); }
    else { navigator.clipboard?.writeText(url).then(() => toast('Your biolink copied')); }
    if (NUM) logQuick('messaged');
}

async function addFavorite() {
    const { ok } = await post(dp.dataset.favUrl, { number: NUM_E164 || NUM, contact_id: CONTACT_ID });
    if (ok) {
        const btn = document.getElementById('fav-toggle');
        btn.querySelector('span').textContent = 'In speed dial';
        toast('Added to speed dial');
    }
}

async function toggleFlag(field) {
    const badge = field === 'is_spam' ? document.getElementById('badge-spam') : document.getElementById('badge-blocked');
    const on = badge.classList.contains('hidden'); // about to turn on
    const { ok, data } = await post(dp.dataset.flagUrl, { number: NUM_E164 || NUM, [field]: on });
    if (!ok || !data?.data) return;
    const spam = data.data.is_spam, blocked = data.data.is_blocked;
    document.getElementById('badge-spam').classList.toggle('hidden', !spam);
    document.getElementById('badge-blocked').classList.toggle('hidden', !blocked);
    document.querySelector('#spam-toggle span').textContent = spam ? 'Unmark spam' : 'Mark spam';
    document.querySelector('#block-toggle span').textContent = blocked ? 'Unblock' : 'Block';
}

document.querySelectorAll('.outcome-chip').forEach(c => {
    c.addEventListener('click', () => {
        document.querySelectorAll('.outcome-chip').forEach(x => x.classList.remove('active'));
        if (selectedOutcome === c.dataset.outcome) { selectedOutcome = null; return; }
        c.classList.add('active');
        selectedOutcome = c.dataset.outcome;
    });
});

function logQuick(outcome) {
    // Fire-and-forget quick log from the action bar.
    post(dp.dataset.logUrl, { number: NUM_E164 || NUM, contact_id: CONTACT_ID, outcome });
}

async function saveLog() {
    const note = document.getElementById('log-note').value.trim();
    const tag = document.getElementById('log-tag').value.trim();
    const { ok } = await post(dp.dataset.logUrl, {
        number: NUM_E164 || NUM, contact_id: CONTACT_ID,
        outcome: selectedOutcome, note: note || null, tag: tag || null,
    });
    if (ok) { toast('Call logged'); location.reload(); }
}

async function setCallback() {
    const at = document.getElementById('cb-at').value;
    if (!at) { toast('Pick a date & time'); return; }
    const note = document.getElementById('log-note').value.trim();
    const { ok, data } = await post(dp.dataset.callbackUrl, {
        number: NUM_E164 || NUM, contact_id: CONTACT_ID, callback_at: at, note: note || null,
    });
    if (ok) { toast('Reminder set'); location.reload(); }
    else if (data?.error) toast(data.error.message || 'Could not set reminder');
}
</script>
@endsection
