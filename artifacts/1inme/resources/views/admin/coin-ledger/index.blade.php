@extends('admin.layouts.app')
@section('title', 'Coin Ledger')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold ak-strong">Coin Ledger</h1>
            <p class="text-sm ak-muted">Day-by-day coin flow across all user wallets.</p>
        </div>
        <a href="{{ route('admin.coin-ledger.export', request()->only(['type','from','to','q','user_id'])) }}"
           class="px-4 py-2 rounded-lg border text-sm ak-soft hover:bg-white/5" style="border-color: var(--border);">
            <i class="fas fa-file-csv mr-1.5"></i>Export CSV
        </a>
    </div>

    @if($drillUser)
        <div class="flex items-center gap-3 p-3 rounded-lg border text-sm" style="border-color: var(--border);" data-testid="ledger-drill-user">
            <i class="fas fa-user ak-muted"></i>
            <span class="ak-soft">Showing ledger for <strong class="ak-strong">{{ $drillUser->name }}</strong> ({{ $drillUser->email }})</span>
            <a href="{{ route('admin.coin-ledger.index', collect(request()->only(['type','from','to']))->filter()->all()) }}" class="ml-auto text-blue-400 hover:underline">Clear user filter</a>
        </div>
    @endif

    {{-- Platform summary tiles over the filtered range --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3" data-testid="ledger-summary">
        <div class="rounded-xl border p-4" style="border-color: var(--border);">
            <p class="text-[11px] uppercase tracking-wider ak-muted">Coins in</p>
            <p class="text-xl font-bold text-emerald-400 mt-1">+{{ number_format($summary->coins_in ?? 0) }}</p>
        </div>
        <div class="rounded-xl border p-4" style="border-color: var(--border);">
            <p class="text-[11px] uppercase tracking-wider ak-muted">Coins out</p>
            <p class="text-xl font-bold text-red-400 mt-1">−{{ number_format($summary->coins_out ?? 0) }}</p>
        </div>
        <div class="rounded-xl border p-4" style="border-color: var(--border);">
            <p class="text-[11px] uppercase tracking-wider ak-muted">Net</p>
            @php $net = (int) ($summary->net ?? 0); @endphp
            <p class="text-xl font-bold {{ $net >= 0 ? 'text-emerald-400' : 'text-red-400' }} mt-1">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }}</p>
        </div>
        <div class="rounded-xl border p-4" style="border-color: var(--border);">
            <p class="text-[11px] uppercase tracking-wider ak-muted">Entries</p>
            <p class="text-xl font-bold ak-strong mt-1">{{ number_format($summary->entries ?? 0) }}</p>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 p-4 rounded-xl border" style="border-color: var(--border);">
        @if($filters['user_id'] ?? null)
            <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
        @endif
        <div>
            <label class="block text-xs ak-muted mb-1">Type</label>
            <select name="type" class="px-3 py-2 rounded-lg border bg-transparent text-sm ak-soft" style="border-color: var(--border);">
                <option value="">All</option>
                @foreach(\App\Modules\User\Models\WalletTransaction::TYPES as $t)
                    <option value="{{ $t }}" {{ ($filters['type'] ?? '') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs ak-muted mb-1">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-2 rounded-lg border bg-transparent text-sm ak-soft" style="border-color: var(--border);">
        </div>
        <div>
            <label class="block text-xs ak-muted mb-1">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-2 rounded-lg border bg-transparent text-sm ak-soft" style="border-color: var(--border);">
        </div>
        @unless($filters['user_id'] ?? null)
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs ak-muted mb-1">User (name or email)</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search users…" class="w-full px-3 py-2 rounded-lg border bg-transparent text-sm ak-soft" style="border-color: var(--border);">
        </div>
        @endunless
        <button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg">Filter</button>
        <a href="{{ route('admin.coin-ledger.index') }}" class="px-3 py-2 ak-muted text-sm">Clear</a>
    </form>

    @if(empty($days))
        <div class="rounded-2xl border p-10 text-center ak-muted" style="border-color: var(--border);">
            No wallet transactions match.
        </div>
    @else
        <div class="space-y-4">
        @foreach($days as $day => $txs)
            @php
                $dt = \Carbon\Carbon::parse($day);
                $totals = $dayTotals[$day] ?? null;
            @endphp
            <div class="rounded-2xl border overflow-hidden" style="border-color: var(--border);" data-testid="ledger-day" data-day="{{ $day }}">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3" style="background: var(--surface-2, rgba(255,255,255,.04));">
                    <div class="text-sm font-semibold ak-strong">
                        {{ $dt->format('D, M j, Y') }}
                        @if($dt->isToday())<span class="ml-2 text-[10px] px-2 py-0.5 rounded-full bg-blue-500/20 text-blue-400 uppercase tracking-wider">Today</span>@endif
                    </div>
                    @if($totals)
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-emerald-400">In +{{ number_format($totals->coins_in) }}</span>
                        <span class="text-red-400">Out −{{ number_format($totals->coins_out) }}</span>
                        <span class="{{ (int)$totals->net >= 0 ? 'text-emerald-400' : 'text-red-400' }} font-semibold">Net {{ (int)$totals->net >= 0 ? '+' : '' }}{{ number_format($totals->net) }}</span>
                    </div>
                    @endif
                </div>
                <table class="w-full text-sm">
                    <tbody>
                    @foreach($txs as $tx)
                        <tr class="border-t" style="border-color: var(--border);">
                            <td class="p-3 ak-muted whitespace-nowrap w-16">{{ $tx->created_at->format('H:i') }}</td>
                            <td class="p-3">
                                <a href="{{ route('admin.coin-ledger.index', array_merge(collect($filters)->except(['q','user_id'])->filter()->all(), ['user_id' => $tx->user_id])) }}"
                                   class="text-blue-400 hover:underline">{{ $tx->user->name ?? ('#'.$tx->user_id) }}</a>
                                <div class="text-xs ak-muted">{{ $tx->user->email ?? '' }}</div>
                            </td>
                            <td class="w-28"><span class="px-2 py-0.5 rounded-full text-xs ak-soft" style="background: var(--surface-2, rgba(255,255,255,.08));">{{ $tx->type }}</span></td>
                            <td class="p-3 ak-soft">{{ $tx->reason ?? ucfirst($tx->type) }}</td>
                            <td class="text-right font-semibold whitespace-nowrap {{ $tx->delta_coins >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                            </td>
                            <td class="text-right pr-4 pl-4 ak-soft whitespace-nowrap">{{ number_format($tx->balance_after) }} <span class="ak-muted text-xs">bal</span></td>
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
