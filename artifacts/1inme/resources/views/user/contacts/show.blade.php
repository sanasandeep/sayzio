@extends('user.layouts.app')

@section('title', $contact->nameForDisplay())

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.contacts.index') }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back to contacts
    </a>

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

    <div class="card-premium p-6">
        <div class="flex items-start gap-4 mb-5">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white flex-shrink-0" style="background: linear-gradient(135deg,#7c3aed,#ec4899);">
                @if($contact->photoUrl())
                    <img src="{{ $contact->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                @else
                    {{ $contact->initials() }}
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold" style="color:var(--text-primary);">{{ $contact->nameForDisplay() }}</h1>
                @if($contact->organization)
                    <p class="text-sm" style="color:var(--text-muted);">{{ $contact->job_title ? $contact->job_title . ' · ' : '' }}{{ $contact->organization }}</p>
                @endif
                @if($contact->google_resource_name)
                    <span class="inline-block mt-2 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider" style="background:rgba(236,72,153,.15);color:#f472b6">
                        <i class="fab fa-google mr-1"></i> Synced
                    </span>
                @endif
            </div>
            <a href="{{ route('user.contacts.edit', $contact) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                <i class="fas fa-pen mr-1"></i> Edit
            </a>
        </div>

        @if($biolinkPreview)
        <div class="mb-5 p-4 rounded-xl" style="background:linear-gradient(135deg,rgba(236,72,153,.08),rgba(124,58,237,.08));border:1px solid rgba(236,72,153,.20);">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#ec4899,#7c3aed);">
                        {{ mb_strtoupper(mb_substr($biolinkPreview['user']->name ?? '?', 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold uppercase tracking-wider" style="color:#f472b6;">1INME Link in Bio</div>
                        <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">{{ $biolinkPreview['user']->name }}</div>
                        @if($biolinkPreview['url'])
                            <a href="{{ $biolinkPreview['url'] }}" target="_blank" class="text-xs truncate" style="color:#a78bfa;">{{ $biolinkPreview['url'] }}</a>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('user.contacts.biolink.detach', $contact) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Detach this biolink?', message: 'It will not auto-attach again on future syncs unless you re-attach.', confirmText: 'Detach', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                    @csrf
                    <button class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                        Detach
                    </button>
                </form>
            </div>
            @php($_smsPhone = $contact->phones->first(fn($p) => !empty($p->value_e164)) ?? $contact->phones->first())
            @if($biolinkPreview['url'] && $_smsPhone)
                @php($_smsTo = $_smsPhone->value_e164 ?: $_smsPhone->value)
                @php($_smsBody = ($contact->nameForDisplay() ? 'Hey ' . $contact->nameForDisplay() . ', ' : 'Hey, ') . "here's my 1INME page: " . $biolinkPreview['url'])
                <div class="mt-3 pt-3 flex flex-wrap items-center gap-2" style="border-top:1px dashed rgba(236,72,153,.20);">
                    <a href="sms:{{ $_smsTo }}?body={{ rawurlencode($_smsBody) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                       style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                        <i class="fas fa-comment-sms mr-1"></i> Text Link in Bio to {{ $_smsTo }}
                    </a>
                    <form method="POST" action="{{ route('user.contacts.biolink.sms', $contact) }}" class="inline">
                        @csrf
                        <input type="hidden" name="to" value="{{ $_smsTo }}">
                        <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                                style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)"
                                title="Send via your configured SMS gateway (desktop fallback)">
                            <i class="fas fa-paper-plane mr-1"></i> Send via gateway
                        </button>
                    </form>
                </div>
            @endif
        </div>
        @else
            <form method="POST" action="{{ route('user.contacts.biolink.attach', $contact) }}" class="mb-5">
                @csrf
                <button class="text-xs font-medium" style="color:#a78bfa;">
                    <i class="fas fa-link mr-1"></i> Re-check for a 1INME Link in Bio
                </button>
            </form>
        @endif

        @if($contact->phones->isNotEmpty())
        <div class="mb-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:var(--text-faint);">Phone</h3>
            @foreach($contact->phones as $p)
                <div class="flex items-center justify-between py-2" style="border-top: 1px solid rgba(255,255,255,.06);">
                    <div>
                        <div class="text-sm" style="color:var(--text-primary);">{{ $p->value }}</div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $p->label ?: 'Phone' }}</div>
                    </div>
                    <div class="flex gap-1">
                        <a href="tel:{{ $p->value_e164 ?: $p->value }}" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                            <i class="fas fa-phone mr-1"></i> Call
                        </a>
                        <a href="{{ route('user.dialer.profile', ['number' => $p->value_e164 ?: $p->value, 'contact' => $contact->id]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                            <i class="fas fa-id-card mr-1"></i> Profile
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        @if($contact->emails->isNotEmpty())
        <div class="mb-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:var(--text-faint);">Email</h3>
            @foreach($contact->emails as $e)
                <div class="flex items-center justify-between py-2" style="border-top: 1px solid rgba(255,255,255,.06);">
                    <div>
                        <div class="text-sm" style="color:var(--text-primary);">{{ $e->value }}</div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $e->label ?: 'Email' }}</div>
                    </div>
                    <a href="mailto:{{ $e->value }}" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.20)">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </a>
                </div>
            @endforeach
        </div>
        @endif

        @if($contact->notes)
            <div class="mt-5 pt-4" style="border-top: 1px solid rgba(255,255,255,.06);">
                <h3 class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:var(--text-faint);">Notes</h3>
                <p class="text-sm whitespace-pre-line" style="color:var(--text-muted);">{{ $contact->notes }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
