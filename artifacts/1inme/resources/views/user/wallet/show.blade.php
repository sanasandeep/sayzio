@extends('user.layouts.app')
@section('title', 'Wallet')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    @if(!empty($locked))
        @include('user.partials._feature-locked', [
            'title'       => 'Wallet & Coins',
            'icon'        => 'fa-solid fa-coins',
            'description' => 'Your coin wallet powers the pay-as-you-go side of the platform, top up a balance once and spend it across the features that bill in coins.',
            'offers'      => [
                'Keep a coin balance you can see and top up at any time',
                'Buy coins in packages priced in your local currency',
                'Pay for AI features straight from your wallet',
                'Cover usage overages (like extra API calls) without a separate purchase',
            ],
            'cta'         => 'Contact your admin',
            'ctaUrl'      => null,
        ])
    @else
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40">Wallet balance</p>
            <p class="text-4xl font-bold text-amber-300 mt-1">{{ number_format($wallet->balance) }} 🪙</p>
            @if($rate > 0)
                <p class="text-xs text-white/30 mt-1">≈ {{ $currency }} {{ number_format($wallet->balance / $rate, 2) }} ({{ $rate }} coins per 1 {{ $currency }})</p>
            @endif
        </div>
        <a href="{{ route('user.wallet.buy') }}" class="px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">Buy Coins</a>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-white font-semibold">Recent transactions</h3>
            <a href="{{ route('user.wallet.transactions') }}" class="text-xs text-blue-300 hover:underline">View all</a>
        </div>
        @if($transactions->isEmpty())
            <p class="text-sm text-white/40">No transactions yet.</p>
        @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">When</th><th class="text-left">Type</th>
                <th class="text-right">Δ Coins</th><th class="text-right">Balance</th>
                <th class="text-left pl-3">Reason</th>
            </tr></thead>
            <tbody>
            @foreach($transactions as $tx)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white/60">{{ $tx->created_at->diffForHumans() }}</td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                    <td class="text-right font-semibold {{ $tx->delta_coins >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                        {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                    </td>
                    <td class="text-right text-white/80">{{ number_format($tx->balance_after) }}</td>
                    <td class="pl-3 text-white/50">{{ $tx->reason ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif
</div>
@endsection
