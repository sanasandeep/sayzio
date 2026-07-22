@extends('admin.layouts.app')
@section('title', 'AI Usage: '.$user->name)
@section('page-title', 'AI Usage, '.$user->name)

@section('content')
<div class="max-w-4xl space-y-5">
    <a href="{{ route('admin.ai-usage.index') }}" class="text-xs text-blue-300 hover:underline ak-blue">← Back to report</a>

    <div class="glass rounded-2xl border border-white/10 p-6 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-white/40 ak-note">Coin balance</p>
            <p class="text-4xl font-bold text-blue-300 mt-1 ak-blue">{{ number_format($balance) }}</p>
            <p class="text-xs text-white/40 mt-1 ak-note">AI usage is paid from the coin wallet.</p>
        </div>
        <div>
            <p class="text-xs text-white/40 ak-note">{{ $user->email }}</p>
            <p class="text-[11px] text-white/30 mt-1 font-mono ak-note">user #{{ $user->id }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.ai-usage.adjust', $user) }}"
          class="glass rounded-2xl border border-white/10 p-6 space-y-3">
        @csrf
        <h3 class="font-semibold text-white ak-strong">Manual adjustment</h3>
        <p class="text-xs text-white/40 ak-note">Use a negative number to debit coins. Reason is recorded in the ledger.</p>
        @if($errors->any())
            <div class="text-xs text-red-300 ak-red">{{ $errors->first() }}</div>
        @endif
        @if(session('error'))
            <div class="text-xs text-red-300 ak-red">{{ session('error') }}</div>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <input type="number" name="delta" required placeholder="±coins"
                   class="w-32 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm ak-strong ak-input">
            <input type="text" name="reason" required maxlength="500" placeholder="Reason"
                   class="flex-1 min-w-[260px] bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-white text-sm ak-strong ak-input">
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Apply</button>
        </div>
    </form>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h3 class="font-semibold text-white mb-3 ak-strong">Recent transactions</h3>
        @if($transactions->isEmpty())
            <p class="text-sm text-white/40 ak-note">No AI activity yet.</p>
        @else
            <table class="w-full text-xs">
                <thead><tr class="text-white/40 uppercase tracking-wider ak-note">
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
                        <td class="py-2 text-white/60 ak-muted">{{ $tx->created_at->diffForHumans() }}</td>
                        <td><span class="px-2 py-0.5 rounded-full bg-white/10 text-white/70 ak-strong">{{ $tx->type }}</span></td>
                        <td class="text-white/60 ak-muted">{{ $feat ?? '—' }} <span class="text-white/30 ak-note">{{ $mdl ? '· '.$mdl : '' }}</span></td>
                        <td class="text-right font-semibold {{ $tx->delta_coins >= 0 ? 'text-emerald-300 ak-green' : 'text-red-300 ak-red' }}">
                            {{ $tx->delta_coins >= 0 ? '+' : '' }}{{ number_format($tx->delta_coins) }}
                        </td>
                        <td class="text-right text-white/80 ak-strong">{{ number_format($tx->balance_after) }}</td>
                        <td class="pl-3 text-white/50 ak-muted">{{ $tx->reason ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
