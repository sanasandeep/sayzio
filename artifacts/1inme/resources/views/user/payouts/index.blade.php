@extends('user.layouts.app', ['pageTitle' => 'Earnings & Payouts'])

@section('content')
<div class="max-w-6xl mx-auto py-8 px-4">

    @if(session('success'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-2">
            <i class="fas fa-check-circle mt-0.5"></i><span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-2">
            <i class="fas fa-circle-exclamation mt-0.5"></i><span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- ─────── Header ─────── --}}
    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Earnings & Payouts</h1>
            <p class="text-sm text-slate-600 mt-1">
                Connect a payout provider to receive subscriptions, tips, and per-post unlocks.
                <strong>1INME takes 0% of your earnings</strong> &mdash; the fee shown next to each
                provider is theirs.
            </p>
        </div>
        <div class="text-xs text-slate-500 inline-flex items-center gap-2">
            <i class="fas fa-shield-halved text-emerald-500"></i> Hosted onboarding &middot; KYC handled by the provider
        </div>
    </div>

    {{-- ─────── Adult-friendly callout ─────── --}}
    @if($adultEnabled)
        <div class="mb-6 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-900 text-sm flex items-start gap-2">
            <i class="fas fa-circle-info mt-0.5 text-rose-600"></i>
            <div>
                Your profile has <strong>adult content (18+)</strong> enabled, so your default payout
                must use an <strong>adult-friendly processor</strong> (CCBill or Segpay). Stripe,
                PayPal, and Razorpay won't accept adult merchant accounts.
            </div>
        </div>
    @endif

    {{-- ─────── Connections summary ─────── --}}
    @if($default)
        <div class="mb-8 p-5 rounded-2xl border border-slate-200 bg-white shadow-sm flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide font-semibold text-slate-500">Current default payout</div>
                <div class="mt-1 text-lg font-bold text-slate-900 inline-flex items-center gap-2">
                    <i class="{{ $providers[$default->provider]['icon'] ?? 'fas fa-credit-card' }} text-xl"
                       style="color: {{ $providers[$default->provider]['tint'] ?? '#475569' }};"></i>
                    {{ $providers[$default->provider]['name'] ?? ucfirst($default->provider) }}
                </div>
                <div class="mt-1 text-xs text-{{ $default->statusColor() }}-600 font-semibold inline-flex items-center gap-1.5">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-{{ $default->statusColor() }}-500"></span>
                    {{ $default->statusLabel() }}
                    @if($default->status_reason)<span class="text-slate-500 font-normal"> &middot; {{ $default->status_reason }}</span>@endif
                </div>
            </div>
            <div class="text-xs text-slate-400">
                @if($default->last_sync_at)Synced {{ $default->last_sync_at->diffForHumans() }}@else Not yet synced @endif
            </div>
        </div>
    @endif

    {{-- ─────── Provider grid ─────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($providers as $slug => $p)
            @php $conn = $connections[$slug] ?? null; @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-5 flex flex-col {{ $p['adult_friendly'] ? 'ring-1 ring-rose-200' : '' }}">
                <div class="flex items-start gap-3">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl"
                         style="background-color: {{ $p['tint'] }};">
                        <i class="{{ $p['icon'] }}"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-slate-900">{{ $p['name'] }}</h3>
                            @if($p['adult_friendly'])
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 text-[10px] font-bold uppercase tracking-wide">
                                    <i class="fas fa-fire"></i> 18+ ok
                                </span>
                            @endif
                            @if($conn?->is_default)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold uppercase tracking-wide">
                                    <i class="fas fa-star"></i> Default
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-600 mt-1 leading-snug">{{ $p['short'] }}</p>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-1.5 text-xs">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Countries</dt><dd class="text-slate-800 text-right">{{ $p['countries'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Payout speed</dt><dd class="text-slate-800 text-right">{{ $p['payout_speed'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Provider fees</dt><dd class="text-slate-800 text-right">{{ $p['fees'] }}</dd></div>
                </dl>

                @if($conn)
                    <div class="mt-4 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-{{ $conn->statusColor() }}-500"></span>
                            <span class="font-semibold text-slate-700">{{ $conn->statusLabel() }}</span>
                            @if($conn->payouts_enabled)<span class="text-emerald-700">&middot; payouts on</span>@endif
                            @if($conn->charges_enabled)<span class="text-emerald-700">&middot; charges on</span>@endif
                        </div>
                        @if($conn->status_reason)
                            <div class="text-slate-500 mt-1">{{ $conn->status_reason }}</div>
                        @endif
                        @if($conn->account_id)
                            <div class="text-slate-400 mt-1 truncate"><span class="font-mono">{{ $conn->account_id }}</span></div>
                        @endif
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2 mt-auto pt-4">
                    @if(!$conn || !$conn->payouts_enabled)
                        <a href="{{ route('user.payouts.connect', ['provider' => $slug]) }}"
                           class="px-3 py-2 rounded-lg text-xs font-semibold text-white"
                           style="background-color: {{ $p['tint'] }};">
                            <i class="fas fa-plug"></i> {{ $conn ? 'Resume' : 'Connect' }}
                        </a>
                    @endif
                    @if($conn)
                        @if(!$conn->is_default && (!$adultEnabled || $conn->adult_friendly))
                            <form method="POST" action="{{ route('user.payouts.set-default', ['connection' => $conn->id]) }}">
                                @csrf
                                <button class="px-3 py-2 rounded-lg text-xs font-semibold bg-slate-900 text-white hover:bg-slate-800">
                                    <i class="fas fa-star"></i> Make default
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('user.payouts.sync', ['connection' => $conn->id]) }}">
                            @csrf
                            <button class="px-3 py-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">
                                <i class="fas fa-arrows-rotate"></i> Refresh
                            </button>
                        </form>
                        @php $dash = \App\Services\CreatorPayouts\PayoutProviderRegistry::adapter($slug)->dashboardUrl($conn); @endphp
                        @if($dash)
                            <a href="{{ $dash }}" target="_blank" rel="noopener"
                               class="px-3 py-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200">
                                <i class="fas fa-arrow-up-right-from-square"></i> Manage
                            </a>
                        @endif
                        <form method="POST" action="{{ route('user.payouts.destroy', ['connection' => $conn->id]) }}"
                              onsubmit="return confirm('Disconnect {{ $p['name'] }}? Your account at the provider is not deleted.');">
                            @csrf @method('DELETE')
                            <button class="px-3 py-2 rounded-lg text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </form>
                    @endif
                    <a href="{{ $p['docs_url'] }}" target="_blank" rel="noopener"
                       class="ml-auto text-xs text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
                        Learn more <i class="fas fa-arrow-up-right-from-square text-[10px]"></i>
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    @if(!$adultEnabled)
        <div class="mt-8 p-5 rounded-2xl border border-dashed border-slate-300 text-sm text-slate-600">
            <div class="flex items-start gap-3">
                <i class="fas fa-fire text-rose-500 mt-0.5"></i>
                <div class="flex-1">
                    <strong class="text-slate-900">Publishing 18+ content?</strong>
                    Enable the adult-content toggle in
                    <a href="{{ route('user.adult-content.show') }}" class="text-violet-700 hover:underline font-semibold">Adult content settings</a>
                    to unlock CCBill and Segpay as payout providers.
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
