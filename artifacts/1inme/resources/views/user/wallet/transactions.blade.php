@extends('user.layouts.app')
@section('title', 'Coin Ledger')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Coin ledger</h1>
            <p class="text-sm text-white/40">Balance: <span class="text-amber-300 font-semibold">{{ number_format($wallet->balance) }} 🪙</span></p>
        </div>
        <a href="{{ route('user.wallet.show') }}" class="text-sm text-blue-300 hover:underline">← Back to wallet</a>
    </div>

    {{-- Period summary tiles over the filtered range --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3" data-testid="ledger-summary">
        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Coins purchased</p>
            <p class="text-xl font-bold text-emerald-300 mt-1">+{{ number_format($summary->coins_in ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Coins spent</p>
            <p class="text-xl font-bold text-red-300 mt-1">−{{ number_format($summary->coins_out ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Net change</p>
            @php $net = (int) ($summary->net ?? 0); @endphp
            <p class="text-xl font-bold {{ $net >= 0 ? 'text-emerald-300' : 'text-red-300' }} mt-1">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }}</p>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/[0.03] p-4">
            <p class="text-[11px] uppercase tracking-wider text-white/40">Entries</p>
            <p class="text-xl font-bold text-white/80 mt-1">{{ number_format($summary->entries ?? 0) }}</p>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 p-4 rounded-xl border border-white/10 bg-white/[0.03]">
        <div>
            <label class="block text-xs text-white/50 mb-1">Type</label>
            <select name="type" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                <option value="">All</option>
                @foreach(\App\Modules\User\Models\WalletTransaction::TYPES as $t)
                    <option value="{{ $t }}" {{ ($filters['type'] ?? '') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-white/50 mb-1">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
        </div>
        <div>
            <label class="block text-xs text-white/50 mb-1">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
        </div>
        <button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg">Filter</button>
        <a href="{{ route('user.wallet.transactions') }}" class="px-3 py-2 text-white/60 text-sm">Clear</a>
        <a href="{{ route('user.wallet.transactions.export', request()->only(['type','from','to'])) }}"
           class="ml-auto px-4 py-2 border border-white/15 text-white/80 text-sm rounded-lg hover:bg-white/5">
            <i class="fa-solid fa-file-csv mr-1.5"></i>Export CSV
        </a>
    </form>

    @if(empty($days))
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center text-white/40">
            No transactions match.
        </div>
    @else
        <div class="space-y-4">
        @foreach($days as $day => $txs)
            @php
                $dt = \Carbon\Carbon::parse($day);
                $totals = $dayTotals[$day] ?? null;
            @endphp
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] overflow-hidden" data-testid="ledger-day" data-day="{{ $day }}">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 bg-white/5">
                    <div class="text-sm font-semibold text-white">
                        {{ $dt->format('D, M j, Y') }}
                        @if($dt->isToday())<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-300 uppercase tracking-wider">Today</span>@endif
                    </div>
                    @if($totals)
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-emerald-300">In +{{ number_format($totals->coins_in) }}</span>
                        <span class="text-red-300">Out −{{ number_format($totals->coins_out) }}</span>
                        <span class="{{ (int)$totals->net >= 0 ? 'text-emerald-300' : 'text-red-300' }} font-semibold">Net {{ (int)$totals->net >= 0 ? '+' : '' }}{{ number_format($totals->net) }}</span>
                    </div>
                    @endif
                </div>
                <table class="w-full text-sm">
                    <tbody>
                    @foreach($txs as $tx)
                        <tr class="border-t border-white/5">
                            <td class="p-3 text-white/50 whitespace-nowrap w-20">{{ $tx->created_at->format('H:i') }}</td>
                            <td class="w-28"><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                            <td class="p-3 text-white/60">{{ $tx->reason ?? ucfirst($tx->type) }}</td>
                            <td class="text-right font-semibold whitespace-nowrap {{ $tx->delta_coins >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                                {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                            </td>
                            <td class="text-right pr-4 pl-4 text-white/70 whitespace-nowrap">{{ number_format($tx->balance_after) }} <span class="text-white/30 text-xs">bal</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
        </div>
    @endif

    {{ $page->links() }}
</div>
@endsection
