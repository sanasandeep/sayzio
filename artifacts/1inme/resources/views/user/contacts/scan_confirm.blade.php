@extends('user.layouts.app')

@section('title', 'Save contact from scan')

@php
    $ex        = $extracted;
    $phones    = array_values(array_filter($ex['phones'] ?? [], fn($p) => trim((string)($p['value'] ?? '')) !== ''));
    $emails    = array_values(array_filter($ex['emails'] ?? [], fn($e) => trim((string)($e['value'] ?? '')) !== ''));
    $name      = $ex['full_name'] ?? trim(($ex['first_name'] ?? '') . ' ' . ($ex['last_name'] ?? ''));
    $company   = $ex['company'] ?? null;
    $title     = $ex['title'] ?? null;
    $hasDups   = count($duplicates) > 0;
@endphp

@section('content')
<div class="max-w-2xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Save this contact',
        'subtitle' => 'Review the extracted details and save in one tap, or open the full editor to make changes.',
        'icon'     => 'fa-address-card',
        'chips'    => [],
    ])

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium"
         style="background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.20);color:#ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Duplicate warning + update-existing CTA --}}
    @if($hasDups)
    <div class="mb-5 rounded-xl p-4"
         style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.20);">
        <p class="text-sm font-semibold mb-2" style="color:#f59e0b;">
            <i class="fas fa-triangle-exclamation mr-1"></i>
            Possible duplicate{{ count($duplicates) === 1 ? '' : 's' }} detected
        </p>
        <ul class="text-xs space-y-1.5 mb-3" style="color:var(--text-muted);">
            @foreach($duplicates as $d)
            <li>
                Existing contact{{ count($d['contacts']) === 1 ? '' : 's' }} share this {{ $d['type'] }}:
                <strong class="font-semibold" style="color:var(--text-primary);">{{ $d['value'] }}</strong> -
                @foreach($d['contacts'] as $c)
                    <a href="{{ route('user.contacts.show', $c['id']) }}" class="underline font-medium" style="color:#90acff;">{{ $c['name'] }}</a>{{ !$loop->last ? ', ' : '' }}
                @endforeach
            </li>
            @endforeach
        </ul>

        {{-- Offer "append to first matching contact" if only one duplicate --}}
        @if(count($duplicates) === 1 && count($duplicates[0]['contacts']) === 1)
        @php $dup = $duplicates[0]['contacts'][0]; @endphp
        <form method="POST" action="{{ route('user.contacts.scan.quick-save', $scan) }}">
            @csrf
            <input type="hidden" name="update_contact_id" value="{{ $dup['id'] }}">
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-sm font-semibold transition"
                    style="background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.35);color:#f59e0b;">
                <i class="fas fa-user-pen text-xs"></i>
                Add new phones & emails to &ldquo;{{ $dup['name'] }}&rdquo;
            </button>
        </form>
        @elseif(count($duplicates) > 0)
        {{-- Multiple possible duplicates: link to each --}}
        <p class="text-xs mt-1" style="color:var(--text-faint);">
            Open a contact above to edit it manually, or save as a new contact below.
        </p>
        @endif
    </div>
    @endif

    {{-- Summary card --}}
    <div class="card-premium p-5 mb-5">
        <div class="flex items-start gap-4">
            @if(!empty($ex['logo_url']))
            <img src="{{ $ex['logo_url'] }}" alt=""
                 class="rounded-xl flex-shrink-0 object-contain"
                 style="width:72px;height:72px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);">
            @else
            <div class="flex-shrink-0 w-[72px] h-[72px] rounded-xl flex items-center justify-center"
                 style="background:rgba(61,107,255,.10);border:1px solid rgba(61,107,255,.18);">
                <i class="fas fa-id-card text-2xl" style="color:#90acff;"></i>
            </div>
            @endif

            <div class="flex-1 min-w-0">
                @if($name)
                <p class="text-base font-bold truncate" style="color:var(--text-primary);">{{ $name }}</p>
                @endif
                @if($title || $company)
                <p class="text-sm mt-0.5" style="color:var(--text-muted);">
                    {{ implode(' · ', array_filter([$title, $company])) }}
                </p>
                @endif
            </div>
        </div>

        @if($phones || $emails)
        <div class="mt-4 space-y-2">
            @foreach($phones as $p)
            <div class="flex items-center gap-2 text-sm" style="color:var(--text-primary);">
                <i class="fas fa-phone text-xs w-4 text-center" style="color:var(--text-faint);"></i>
                <span>{{ $p['value'] }}</span>
                @if(!empty($p['label']))<span class="text-xs" style="color:var(--text-faint);">· {{ $p['label'] }}</span>@endif
            </div>
            @endforeach
            @foreach($emails as $e)
            <div class="flex items-center gap-2 text-sm" style="color:var(--text-primary);">
                <i class="fas fa-envelope text-xs w-4 text-center" style="color:var(--text-faint);"></i>
                <span>{{ $e['value'] }}</span>
                @if(!empty($e['label']))<span class="text-xs" style="color:var(--text-faint);">· {{ $e['label'] }}</span>@endif
            </div>
            @endforeach
        </div>
        @endif

        @if(empty($name) && empty($phones) && empty($emails) && empty($company))
        <p class="text-sm mt-4" style="color:var(--text-muted);">
            <i class="fas fa-circle-info mr-1 text-amber-400"></i>
            No contact fields were extracted. Use &ldquo;Review & edit&rdquo; to fill them in manually.
        </p>
        @endif
    </div>

    {{-- Primary save action --}}
    <form method="POST" action="{{ route('user.contacts.scan.quick-save', $scan) }}" class="mb-3">
        @csrf
        <button type="submit"
                class="w-full py-3 rounded-xl text-sm font-bold text-white transition"
                style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
            <i class="fas fa-user-plus mr-1.5"></i> Save as new contact
        </button>
    </form>

    {{-- Secondary actions --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('user.contacts.scan.show', ['scan' => $scan->id, 'from' => $from]) }}"
           class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-center transition"
           style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
            <i class="fas fa-pen-to-square mr-1 text-xs"></i> Review &amp; edit
        </a>
        <a href="{{ route('user.' . ($from === 'dialer' ? 'dialer.index' : 'contacts.index')) }}"
           class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-center transition"
           style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-muted);">
            <i class="fas fa-xmark mr-1 text-xs"></i> Cancel
        </a>
    </div>
</div>
@endsection
