@extends('admin.layouts.app')
@section('title', 'AI Usage — '.$user->name)
@section('page-title', 'AI Usage — '.$user->name)

@section('content')
<div class="max-w-4xl space-y-5">
    <a href="{{ route('admin.ai-usage.index') }}" class="text-xs text-violet-300 hover:underline">← Back to report</a>

    <div class="glass rounded-2xl border border-white/10 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40">Coin balance</p>
            <p class="text-4xl font-bold text-violet-300 mt-1">{{ number_format($balance) }}</p>
            <p class="text-xs text-white/40 mt-1">AI usage is paid from the coin wallet.</p>
        </div>
        <div>
            <p class="text-xs text-white/40">{{ $user->email }}</p>
            <p class="text-[11px] text-white/30 mt-1 font-mono">user #{{ $user->id }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.ai-usage.adjust', $user) }}"
          class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        @csrf
        <h3 class="font-semibold text-white">Manual adjustment</h3>
        <p class="text-xs text-white/40">Use a negative number to debit coins. Reason is recorded in the ledger.</p>
        @if($errors->any())
            <div class="text-xs text-red-300">{{ $errors->first() }}</div>
        @endif
        @if(session('error'))
            <div class="text-xs text-red-300">{{ session('error') }}</div>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <input type="number" name="delta" required placeholder="±coins"
                   class="w-32 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
            <input type="text" name="reason" required maxlength="500" placeholder="Reason"
                   class="flex-1 min-w-[260px] bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm">
            <button class="px-4 py-2 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">Apply</button>
        </div>
    </form>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="font-semibold text-white mb-3">Recent transactions</h3>
        @if($transactions->isEmpty())
            <p class="text-sm text-white/40">No AI activity yet.</p>
        @else
            <table class="w-full text-xs">
                <thead><tr class="text-white/40 uppercase tracking-wider">
                    <th class="text-left py-2">When</th>
                    <th class="text-left">Type</th>
                    <th class="text-left">Feature / model</th>
                    <th class="text-right">Δ</th>
                    <th class="text-right">Balance</th>
                    <th class="text-left pl-3">Reason</th>
                </tr></thead>
                <tbody>
                @foreach($transactions as $tx)
                    @php
                        $meta = is_array($tx->meta) ? $tx->meta : [];
                        $feat = $meta['feature'] ?? null;
                        $mdl  = $meta['model'] ?? null;
                    @endphp
                    <tr class="border-t border-white/5">
                        <td class="py-2 text-white/60">{{ $tx->created_at->diffForHumans() }}</td>
                        <td><span class="px-2 py-0.5 rounded-full bg-white/10 text-white/70">{{ $tx->type }}</span></td>
                        <td class="text-white/60">{{ $feat ?? '—' }} <span class="text-white/30">{{ $mdl ? '· '.$mdl : '' }}</span></td>
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
</div>
@endsection
