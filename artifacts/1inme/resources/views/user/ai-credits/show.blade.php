@extends('user.layouts.app')
@section('title', 'AI Credits')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40">AI credits</p>
            <p class="text-4xl font-bold text-violet-300 mt-1">{{ number_format($balance->balance) }} ✦</p>
            <p class="text-xs text-white/30 mt-1">
                Spent {{ number_format($balance->lifetime_spent) }}
                · purchased {{ number_format($balance->lifetime_purchased) }}
            </p>
        </div>
        <a href="{{ route('user.ai-credits.transactions') }}"
           class="px-4 py-2 bg-white/10 text-white rounded-xl text-sm font-medium hover:bg-white/20">View history</a>
    </div>

    @if($walletEnabled)
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-white font-semibold">Top up with wallet coins</h3>
                    <p class="text-xs text-white/40">Wallet balance: <span class="text-amber-300 font-semibold">{{ number_format($walletBalance) }} 🪙</span> · 1 coin = {{ $walletRate }} credits</p>
                </div>
                <a href="{{ route('user.wallet.show') }}" class="text-xs text-violet-300 hover:underline">Wallet →</a>
            </div>

            @if(empty($packs))
                <p class="text-sm text-white/40">No credit packs are configured. Ask an admin to add some.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($packs as $pack)
                        <form method="POST" action="{{ route('user.ai-credits.buy') }}"
                              class="rounded-xl border border-white/10 bg-white/[0.02] p-4 space-y-3">
                            @csrf
                            <input type="hidden" name="pack_id" value="{{ $pack['id'] }}">
                            <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                            <div>
                                <p class="text-white font-semibold">{{ $pack['label'] }}</p>
                                <p class="text-2xl font-bold text-violet-300 mt-1">{{ number_format($pack['credits']) }} ✦</p>
                                <p class="text-xs text-white/40 mt-1">Costs {{ number_format($pack['wallet_cost']) }} coins</p>
                            </div>
                            <button class="w-full py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700"
                                    {{ $walletBalance < $pack['wallet_cost'] ? 'disabled' : '' }}>
                                {{ $walletBalance < $pack['wallet_cost'] ? 'Not enough coins' : 'Buy with coins' }}
                            </button>
                        </form>
                    @endforeach
                </div>
            @endif

            {{-- Custom amount: priced from the same admin conversion rate. --}}
            <form method="POST" action="{{ route('user.ai-credits.buy') }}"
                  class="mt-5 pt-5 border-t border-white/10 flex flex-wrap items-end gap-3"
                  x-data="{ credits: 1000, rate: {{ (int) $walletRate }} }">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                <div>
                    <label class="text-[10px] uppercase tracking-wider text-white/40 mb-1 block">Custom amount</label>
                    <input type="number" name="credits" min="100" max="1000000" step="100"
                           x-model.number="credits"
                           class="w-32 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
                </div>
                <div class="text-xs text-white/60">
                    = <span class="text-amber-300 font-semibold" x-text="Math.ceil(credits / Math.max(rate,1)).toLocaleString()"></span> coins
                </div>
                <button class="px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-medium hover:bg-violet-700">
                    Buy custom amount
                </button>
            </form>
        </div>
    @else
        <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-6 text-amber-200 text-sm">
            The wallet is currently disabled, so you can't top up AI credits right now.
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-white font-semibold">Recent transactions</h3>
            <a href="{{ route('user.ai-credits.transactions') }}" class="text-xs text-violet-300 hover:underline">View all</a>
        </div>
        @if($transactions->isEmpty())
            <p class="text-sm text-white/40">No transactions yet.</p>
        @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">When</th><th class="text-left">Type</th>
                <th class="text-left">Feature</th>
                <th class="text-right">Δ Credits</th><th class="text-right">Balance</th>
            </tr></thead>
            <tbody>
            @foreach($transactions as $tx)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white/60">{{ $tx->created_at->diffForHumans() }}</td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                    <td class="text-white/50 text-xs">{{ $tx->feature ?? '—' }}{{ $tx->model ? ' · '.$tx->model : '' }}</td>
                    <td class="text-right font-semibold {{ $tx->delta_credits >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                        {{ $tx->delta_credits >= 0 ? '+' : '' }}{{ number_format($tx->delta_credits) }}
                    </td>
                    <td class="text-right text-white/80">{{ number_format($tx->balance_after) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
