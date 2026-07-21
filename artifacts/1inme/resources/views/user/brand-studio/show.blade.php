@extends('user.layouts.app')
@section('title', $kit->name . ' - AI Brand Studio')

@php
    $kindMeta = [
        'biolink'    => ['icon' => 'fa-id-badge',      'label' => 'Link in Bio'],
        'short_link' => ['icon' => 'fa-link',          'label' => 'Short link'],
        'qr_code'    => ['icon' => 'fa-qrcode',        'label' => 'QR code'],
        'form'       => ['icon' => 'fa-rectangle-list','label' => 'Form'],
        'vcard'      => ['icon' => 'fa-address-card',  'label' => 'Digital card'],
    ];
@endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('user.brand-studio.index') }}" class="text-[11px] text-white/40 hover:text-white/70"><i class="fas fa-arrow-left mr-1"></i> AI Brand Studio</a>
            <h1 class="text-2xl font-bold text-white mt-1">{{ $kit->name }}</h1>
            <p class="text-[11px] text-white/40 mt-1">
                {{ $kit->mode === 'bulk' ? 'Bulk variations' : 'Full kit' }} · planned {{ $kit->created_at->diffForHumans() }}
                @if($kit->credits_spent) · {{ $kit->credits_spent }} coins @endif
            </p>
        </div>
        @if($kit->isCreated())
            <span class="px-3 py-1.5 rounded-full text-xs bg-emerald-500/10 border border-emerald-500/20 text-emerald-300"><i class="fas fa-check mr-1"></i> Created</span>
        @else
            <span class="px-3 py-1.5 rounded-full text-xs bg-amber-500/10 border border-amber-500/20 text-amber-300">Awaiting your review</span>
        @endif
    </div>

    @if($kit->request)
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wide text-white/40 mb-1">Your brief</p>
            <p class="text-sm text-white/70">{{ $kit->request }}</p>
        </div>
    @endif

    @if($kit->isCreated())
        {{-- Results --}}
        <div class="space-y-3">
            <h2 class="text-white font-semibold">Created assets</h2>
            @foreach($kit->createdAssets() as $a)
                @php $meta = $kindMeta[$a['kind']] ?? ['icon' => 'fa-cube', 'label' => ucfirst($a['kind'])]; @endphp
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-primary-500/15 text-primary-300 flex items-center justify-center"><i class="fas {{ $meta['icon'] }}"></i></div>
                        <div>
                            <p class="text-white text-sm font-medium">{{ $a['title'] ?? $a['name'] ?? $meta['label'] }}</p>
                            <p class="text-[11px] text-white/40">{{ $meta['label'] }}@if(!empty($a['alias'])) · /{{ $a['alias'] }}@endif @if(!empty($a['purpose']))· {{ $a['purpose'] }}@endif</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($a['kind'] === 'biolink')
                            <a href="{{ route('user.links.blocks.editor', $a['id']) }}" class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white/80 text-sm">Open editor</a>
                        @elseif($a['kind'] === 'qr_code')
                            <a href="{{ route('user.qr-codes.edit', $a['id']) }}" class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white/80 text-sm">Open QR</a>
                        @elseif($a['kind'] === 'form')
                            <a href="{{ route('user.forms.builder', $a['id']) }}" class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white/80 text-sm">Open builder</a>
                        @else
                            <a href="{{ route('user.links.edit', $a['id']) }}" class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white/80 text-sm">Open link</a>
                        @endif
                    </div>
                </div>
            @endforeach
            @foreach(($kit->results['skipped'] ?? []) as $msg)
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm"><i class="fas fa-triangle-exclamation mr-1"></i> {{ $msg }}</div>
            @endforeach
        </div>
    @else
        {{-- Coin context at the second decision point: what this plan cost
             and what's left in the wallet, so confirming (and any follow-on
             AI work) isn't a surprise. Confirming itself is free. --}}
        @if($aiEnabled ?? false)
            @php
                $__lowThreshold = \App\Services\AI\AiUsageCharger::lowBalanceThreshold();
                $__lowBalance   = (int) $balance <= $__lowThreshold;
            @endphp
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-6">
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/40">Coins spent on this plan</p>
                        <p class="text-white font-semibold mt-0.5">{{ number_format((int) $kit->credits_spent) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] uppercase tracking-wide text-white/40">Your coin balance</p>
                        <p class="font-semibold mt-0.5 {{ $__lowBalance ? 'text-amber-300' : 'text-white' }}">{{ number_format((int) $balance) }}</p>
                    </div>
                </div>
                <p class="text-[11px] text-white/35">Creating the selected assets is free; the plan is already paid for.</p>
            </div>
            @if($__lowBalance)
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-200 text-sm flex items-start gap-2">
                    <i class="fas fa-triangle-exclamation mt-0.5 text-amber-300"></i>
                    <span>
                        @if((int) $balance <= 0)
                            You're out of coins, so future AI runs (like re-planning after edits) will fail until you top up.
                        @else
                            Your coin balance is running low, so future AI runs (like re-planning after edits) may not go through.
                        @endif
                        <a href="{{ route('user.wallet.buy') }}" class="font-semibold text-amber-100 underline hover:no-underline">Top up coins</a>.
                    </span>
                </div>
            @endif
        @endif
        {{-- Proposal review --}}
        <form method="POST" action="{{ route('user.brand-studio.confirm', $kit) }}" class="space-y-3">
            @csrf
            <h2 class="text-white font-semibold">Review the plan</h2>
            <p class="text-sm text-white/50">Untick anything you don't want. Nothing is created until you confirm.</p>
            @foreach($kit->proposedAssets() as $i => $a)
                @php $meta = $kindMeta[$a['kind']] ?? ['icon' => 'fa-cube', 'label' => ucfirst($a['kind'])]; @endphp
                <label class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex items-start gap-3 cursor-pointer hover:border-white/20">
                    <input type="checkbox" name="keep[]" value="{{ $i }}" checked class="mt-1 rounded border-white/20 bg-white/[0.05] text-primary-500">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-white text-sm font-medium">{{ $a['title'] ?? $a['name'] ?? $meta['label'] }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-white/[0.06] text-white/50">{{ $meta['label'] }}</span>
                            @if(!empty($a['purpose']))
                                <span class="px-2 py-0.5 rounded-full text-[10px] bg-primary-500/10 border border-primary-500/20 text-primary-300"><i class="fas fa-bullseye mr-1"></i>{{ $a['purpose'] }}</span>
                            @endif
                        </div>
                        @if($a['kind'] === 'short_link' || $a['kind'] === 'qr_code')
                            <p class="text-[12px] text-white/40 truncate mt-0.5">{{ $a['url'] }}</p>
                        @elseif($a['kind'] === 'form')
                            <p class="text-[12px] text-white/40 mt-0.5">Template: {{ $a['template'] }}@if(!empty($a['description'])) · {{ \Illuminate\Support\Str::limit($a['description'], 120) }}@endif</p>
                        @elseif($a['kind'] === 'vcard')
                            <p class="text-[12px] text-white/40 mt-0.5">{{ trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) }}@if(!empty($a['organization'])) · {{ $a['organization'] }}@endif</p>
                        @elseif($a['kind'] === 'biolink')
                            <p class="text-[12px] text-white/40 mt-0.5">
                                {{ count($a['blocks'] ?? []) }} blocks
                                @if(!empty($a['theme_color'])) · theme <span class="inline-block w-3 h-3 rounded-full align-middle" style="background: {{ $a['theme_color'] }}"></span>@endif
                            </p>
                            <p class="text-[11px] text-white/30 mt-0.5">{{ implode(' · ', array_map(fn($b) => $b['type'], array_slice($a['blocks'] ?? [], 0, 8))) }}</p>
                        @endif
                    </div>
                </label>
            @endforeach
            <div class="flex items-center gap-3 pt-2">
                <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-400 text-white text-sm font-medium"><i class="fas fa-check"></i> Create selected assets</button>
            </div>
        </form>
        @php $discardCredits = (int) $kit->credits_spent; @endphp
        <div x-data="{ discardOpen: false }">
            <button type="button" @click="discardOpen = true" class="text-sm text-red-300/80 hover:text-red-300">Discard plan</button>
            <template x-teleport="body">
                <div x-data x-show="discardOpen" x-cloak @keydown.escape.window="discardOpen = false" class="fixed inset-0 z-[90] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="bs-discard-title" style="display: none;">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="discardOpen = false"></div>
                    <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-slate-900/90 backdrop-blur-xl shadow-2xl p-6 space-y-4"
                         x-show="discardOpen"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100">
                        <div class="flex items-start gap-3">
                            <span class="shrink-0 w-10 h-10 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 flex items-center justify-center"><i class="fas fa-trash-can"></i></span>
                            <div class="min-w-0">
                                <h3 id="bs-discard-title" class="text-white font-semibold">Discard this plan?</h3>
                                <p class="text-sm text-white/60 mt-1">
                                    This removes the proposed plan. Nothing has been created yet.
                                    @if($discardCredits > 0)
                                        The {{ number_format($discardCredits) }} {{ Str::plural('credit', $discardCredits) }} spent on planning will be refunded to your wallet.
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if($discardCredits > 0)
                            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300 flex items-center gap-2">
                                <i class="fas fa-coins"></i>
                                <span>{{ number_format($discardCredits) }} {{ Str::plural('credit', $discardCredits) }} will be refunded</span>
                            </div>
                        @endif
                        <div class="flex items-center justify-end gap-3 pt-1">
                            <button type="button" @click="discardOpen = false" class="px-4 py-2.5 rounded-xl text-sm text-white/70 hover:text-white border border-white/10 hover:border-white/20 bg-white/[0.03]">Keep plan</button>
                            <form method="POST" action="{{ route('user.brand-studio.destroy', $kit) }}">
                                @csrf @method('DELETE')
                                <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/90 hover:bg-red-500 text-white text-sm font-medium"><i class="fas fa-trash-can"></i> Discard plan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    @endif
</div>
@endsection
