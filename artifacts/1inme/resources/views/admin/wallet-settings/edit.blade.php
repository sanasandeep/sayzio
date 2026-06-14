@extends('admin.layouts.app')
@section('title', 'Wallet Settings')
@section('page-title', 'Wallet & Coins')

@section('content')
@if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('admin.wallet-settings.update') }}" class="max-w-2xl space-y-6">
    @csrf @method('PUT')

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="text-white font-semibold mb-3">Feature toggle</h3>
        <label class="flex items-center gap-3 text-sm text-white/80">
            <input type="checkbox" name="enabled" value="1" {{ $enabled ? 'checked' : '' }}
                   class="rounded border-white/10 text-violet-400">
            Wallet feature is enabled (customers can buy and spend coins).
        </label>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="text-white font-semibold mb-1">Conversion rates</h3>
        <p class="text-[11px] text-white/40 mb-4">Coins per 1 unit of currency. Used as advisory copy on the Buy Coins page; package prices remain authoritative.</p>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-white/60 mb-1">USD (coins per $1)</label>
                <input type="number" step="0.0001" min="0.0001" name="rates[USD]" value="{{ $rates['USD'] ?? 100 }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            </div>
            <div>
                <label class="block text-xs text-white/60 mb-1">INR (coins per ₹1)</label>
                <input type="number" step="0.0001" min="0.0001" name="rates[INR]" value="{{ $rates['INR'] ?? 1 }}"
                       class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="text-white font-semibold mb-1">API overage rate</h3>
        <p class="text-[11px] text-white/40 mb-4">When a user exceeds their plan's monthly included API-call allowance, extra calls are paid with coins. This sets how many API calls <span class="text-white/70">1 coin</span> buys.</p>
        <div class="max-w-xs">
            <label class="block text-xs text-white/60 mb-1">API calls per coin</label>
            <input type="number" step="1" min="1" name="api_overage_calls_per_coin" value="{{ $apiOveragePerCoin ?? 100 }}"
                   class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm">
            <p class="text-[11px] text-white/30 mt-1">e.g. 100 means a user spends 1 coin for each block of 100 extra API calls.</p>
        </div>
    </div>

    <button class="px-5 py-2.5 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700">Save settings</button>
</form>
@endsection
