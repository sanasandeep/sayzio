@extends('user.layouts.app')
@section('title', 'Wallet Transactions')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Wallet transactions</h1>
            <p class="text-sm text-white/40">Balance: <span class="text-amber-300 font-semibold">{{ number_format($wallet->balance) }} 🪙</span></p>
        </div>
        <a href="{{ route('user.wallet.show') }}" class="text-sm text-violet-300 hover:underline">← Back to wallet</a>
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
        <button class="px-4 py-2 bg-violet-600 text-white text-sm rounded-lg">Filter</button>
        <a href="{{ route('user.wallet.transactions') }}" class="px-3 py-2 text-white/60 text-sm">Clear</a>
    </form>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-white/5"><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left p-3">When</th><th class="text-left">Type</th>
                <th class="text-right">Δ Coins</th><th class="text-right pr-3">Balance</th>
                <th class="text-left p-3">Reason</th>
            </tr></thead>
            <tbody>
            @forelse($page as $tx)
                <tr class="border-t border-white/5">
                    <td class="p-3 text-white/60">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                    <td class="text-right font-semibold {{ $tx->delta_coins >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                        {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                    </td>
                    <td class="text-right pr-3 text-white/80">{{ number_format($tx->balance_after) }}</td>
                    <td class="p-3 text-white/50">{{ $tx->reason ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-white/40 p-8">No transactions match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $page->links() }}
</div>
@endsection
