@extends('admin.layouts.app')
@section('title', 'AI Usage')
@section('page-title', 'AI Usage Report')

@section('content')
<form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
    <div>
        <label class="text-[10px] uppercase tracking-wider text-white/40 block mb-1">Window (days)</label>
        <input type="number" name="days" min="1" max="365" value="{{ $days }}"
               class="w-24 bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm">
    </div>
    <div>
        <label class="text-[10px] uppercase tracking-wider text-white/40 block mb-1">Feature</label>
        <select name="feature" class="bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-white text-sm">
            <option value="">All</option>
            @foreach($features as $f)
                <option value="{{ $f }}" {{ $feature === $f ? 'selected' : '' }}>{{ \App\Modules\User\Models\AiCreditTransaction::featureLabel($f) }}</option>
            @endforeach
        </select>
    </div>
    <button class="px-4 py-1.5 bg-violet-600 text-white rounded-lg text-sm font-medium hover:bg-violet-700">Apply</button>
    <a href="{{ route('admin.ai-engine.edit') }}" class="text-xs text-violet-300 hover:underline ml-auto">AI Engine settings →</a>
</form>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    @foreach([
        ['Spent',     $totals['spent'],     'text-red-300'],
        ['Purchased', $totals['purchased'], 'text-emerald-300'],
        ['Refunded',  $totals['refunded'],  'text-violet-300'],
        ['Adjusted',  $totals['adjusted'],  'text-amber-300'],
    ] as $card)
        <div class="glass rounded-2xl border border-white/10 p-4">
            <p class="text-[10px] uppercase tracking-wider text-white/40">{{ $card[0] }}</p>
            <p class="text-2xl font-bold {{ $card[2] }}">{{ number_format($card[1]) }}</p>
            <p class="text-[10px] text-white/30 mt-1">credits</p>
        </div>
    @endforeach
</div>

@if($featureRows->isNotEmpty())
    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <h3 class="font-semibold text-white mb-1">Per-feature spend</h3>
        <p class="text-xs text-white/40 mb-4">Where credits are being burned in this window.</p>
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">Feature</th>
                <th class="text-right">Calls</th>
                <th class="text-right">Users</th>
                <th class="text-right">Tokens in</th>
                <th class="text-right">Tokens out</th>
                <th class="text-right">Credits spent</th>
            </tr></thead>
            <tbody>
            @foreach($featureRows as $f)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white">
                        <a href="{{ route('admin.ai-usage.index', ['days' => $days, 'feature' => $f->feature]) }}"
                           class="text-violet-300 hover:underline">{{ \App\Modules\User\Models\AiCreditTransaction::featureLabel($f->feature) }}</a>
                        <span class="text-[10px] text-white/30 ml-1">{{ $f->feature }}</span>
                    </td>
                    <td class="text-right text-white/70">{{ number_format($f->calls) }}</td>
                    <td class="text-right text-white/70">{{ number_format($f->users) }}</td>
                    <td class="text-right text-white/50 text-xs">{{ number_format($f->tokens_in) }}</td>
                    <td class="text-right text-white/50 text-xs">{{ number_format($f->tokens_out) }}</td>
                    <td class="text-right text-red-300 font-semibold">{{ number_format($f->spent) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

<div class="glass rounded-2xl border border-white/10 p-6">
    <p class="text-sm text-white/60 mb-4">
        Top spenders in the last {{ $days }} days
        ({{ number_format($totals['calls']) }} AI calls,
        {{ number_format($totals['tokens_in']) }} input tokens,
        {{ number_format($totals['tokens_out']) }} output tokens).
    </p>

    @if($rows->isEmpty())
        <p class="text-sm text-white/40 py-4">No AI usage in this window.</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">User</th>
                <th class="text-right">Balance</th>
                <th class="text-right">Lifetime ⇡ / ⇣</th>
                <th class="text-right">Spent</th>
                <th class="text-right">Purchased</th>
                <th class="text-right">Refunded</th>
                <th class="text-right">Adjusted</th>
                <th class="text-right">Calls</th>
                <th class="text-right">Tokens</th>
                <th></th>
            </tr></thead>
            <tbody>
            @foreach($rows as $r)
                @php
                    $u = $users->get($r->user_id);
                    $b = $balances->get($r->user_id);
                @endphp
                <tr class="border-t border-white/5">
                    <td class="py-2">
                        <div class="text-white">{{ $u->name ?? 'User #'.$r->user_id }}</div>
                        <div class="text-[11px] text-white/40">{{ $u->email ?? '' }}</div>
                    </td>
                    <td class="text-right text-violet-200 font-semibold">{{ number_format((int) ($b->balance ?? 0)) }}</td>
                    <td class="text-right text-white/60 text-xs">
                        {{ number_format((int) ($b->lifetime_purchased ?? 0)) }}
                        <span class="text-white/30">/</span>
                        {{ number_format((int) ($b->lifetime_spent ?? 0)) }}
                    </td>
                    <td class="text-right text-red-300">{{ number_format($r->spent) }}</td>
                    <td class="text-right text-emerald-300">{{ number_format($r->purchased) }}</td>
                    <td class="text-right text-violet-300">{{ number_format($r->refunded) }}</td>
                    <td class="text-right text-amber-300">{{ number_format($r->adjusted) }}</td>
                    <td class="text-right text-white/70">{{ number_format($r->calls) }}</td>
                    <td class="text-right text-white/50 text-xs">{{ number_format($r->tokens_in + $r->tokens_out) }}</td>
                    <td class="text-right">
                        @if($u)
                            <a href="{{ route('admin.ai-usage.show', $u) }}" class="text-violet-300 hover:underline text-xs">View / adjust</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
