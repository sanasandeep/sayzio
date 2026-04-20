@extends('user.layouts.app')

@section('title', 'Dialer profile')

@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.dialer.index') }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back to dialer
    </a>

    <div class="card-premium p-6 mb-4">
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                @if($contact && $contact->photoUrl())
                    <img src="{{ $contact->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                @elseif($contact)
                    {{ $contact->initials() }}
                @else
                    <i class="fas fa-user"></i>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold" style="color:var(--text-primary);">
                    {{ $contact?->nameForDisplay() ?? 'Unknown number' }}
                </h1>
                <div class="text-sm font-mono" style="color:var(--text-muted);">{{ $number }}</div>
                @if($contact && $contact->organization)
                    <p class="text-xs mt-1" style="color:var(--text-muted);">{{ $contact->organization }}</p>
                @endif
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
                @if($number)
                    <a href="tel:{{ $number }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                        <i class="fas fa-phone mr-1"></i> Call
                    </a>
                @endif
                @if($contact && $contact->emails->isNotEmpty())
                    <a href="mailto:{{ $contact->emails->first()->value }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.20)">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </a>
                @endif
                @if($contact)
                    <a href="{{ route('user.contacts.edit', $contact) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </a>
                    <a href="{{ route('user.contacts.show', $contact) }}" class="px-3 py-1.5 rounded-lg text-[11px] font-medium text-center" style="color:var(--text-muted);">
                        Open contact
                    </a>
                @else
                    <a href="{{ route('user.contacts.create', ['phone' => $number]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                        <i class="fas fa-user-plus mr-1"></i> Save
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if($payload['biolink'])
        <div class="card-premium p-6" style="border:1px solid rgba(236,72,153,.30);">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:#f472b6;"><i class="fas fa-link mr-1"></i> 1INME biolink</div>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text-primary);">{{ $payload['biolink']['name'] }}</h2>
                    <p class="text-sm mb-4" style="color:var(--text-muted);">&commat;{{ $payload['biolink']['handle'] }}</p>
                    @if($payload['biolink']['url'])
                        <a href="{{ $payload['biolink']['url'] }}" target="_blank" class="inline-block px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                            Open biolink <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                        </a>
                    @else
                        <p class="text-xs" style="color:var(--text-faint);">This user hasn't published a biolink yet.</p>
                    @endif
                </div>
                @if($contact)
                    <form method="POST" action="{{ route('user.contacts.biolink.detach', $contact) }}" onsubmit="return confirm('Detach this biolink from the contact? It will not auto-attach again on future syncs.')">
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

    @if(!empty($recent) && $recent->isNotEmpty())
        <div class="card-premium p-5 mt-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Recent activity</h3>
            <div class="space-y-2">
                @foreach($recent as $r)
                    <div class="flex items-center justify-between py-1.5" style="border-top: 1px solid rgba(255,255,255,.06);">
                        <div class="text-xs font-mono" style="color:var(--text-primary);">{{ $r->number_e164 }}</div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $r->looked_up_at->diffForHumans() }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
