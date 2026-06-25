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
                <option value="{{ $f }}" {{ $feature === $f ? 'selected' : '' }}>{{ \App\Services\AI\AiFeatureCatalog::featureLabel($f) }}</option>
            @endforeach
        </select>
    </div>
    <button class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Apply</button>
    <a href="{{ route('admin.ai-engine.edit') }}" class="text-xs text-blue-300 hover:underline ml-auto">AI Engine settings →</a>
</form>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    @foreach([
        ['Spent',         $totals['spent'],      'text-red-300',     'coins'],
        ['Refunded',      $totals['refunded'],   'text-blue-300',  'coins'],
        ['Adjusted',      $totals['adjusted'],   'text-amber-300',   'coins'],
        ['Input tokens',  $totals['tokens_in'],  'text-sky-300',     'prompt tokens'],
        ['Output tokens', $totals['tokens_out'], 'text-emerald-300', 'completion tokens'],
    ] as $card)
        <div class="glass rounded-2xl border border-white/10 p-4">
            <p class="text-[10px] uppercase tracking-wider text-white/40">{{ $card[0] }}</p>
            <p class="text-2xl font-bold {{ $card[2] }}">{{ number_format($card[1]) }}</p>
            <p class="text-[10px] text-white/30 mt-1">{{ $card[3] }}</p>
        </div>
    @endforeach
</div>

@isset($cardScanStats)
<div class="glass rounded-2xl border border-white/10 p-6 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-semibold text-white">
            <i class="fas fa-camera-retro text-fuchsia-400 mr-1"></i> Card &amp; brochure scans
        </h3>
        <a href="{{ route('admin.ai-usage.index', ['days' => $days, 'feature' => 'card_scan']) }}"
           class="text-xs text-blue-300 hover:underline">View ledger entries →</a>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div><p class="text-[10px] uppercase text-white/40">Scans</p><p class="text-xl font-bold text-white">{{ number_format($cardScanStats['total']) }}</p></div>
        <div><p class="text-[10px] uppercase text-white/40">Completed</p><p class="text-xl font-bold text-emerald-300">{{ number_format($cardScanStats['completed']) }}</p></div>
        <div><p class="text-[10px] uppercase text-white/40">Failed</p><p class="text-xl font-bold text-red-300">{{ number_format($cardScanStats['failed']) }}</p></div>
        <div><p class="text-[10px] uppercase text-white/40">Distinct users</p><p class="text-xl font-bold text-white">{{ number_format($cardScanStats['users']) }}</p></div>
    </div>
</div>
@endisset

@if($featureRows->isNotEmpty())
    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <h3 class="font-semibold text-white mb-1">Per-feature spend</h3>
        <p class="text-xs text-white/40 mb-4">Where coins are being burned in this window.</p>
        <table class="w-full text-sm">
            <thead><tr class="text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left py-2">Feature</th>
                <th class="text-right">Calls</th>
                <th class="text-right">Users</th>
                <th class="text-right">Tokens in</th>
                <th class="text-right">Tokens out</th>
                <th class="text-right">Coins spent</th>
            </tr></thead>
            <tbody>
            @foreach($featureRows as $f)
                <tr class="border-t border-white/5">
                    <td class="py-2 text-white">
                        <a href="{{ route('admin.ai-usage.index', ['days' => $days, 'feature' => $f->feature]) }}"
                           class="text-blue-300 hover:underline">{{ \App\Services\AI\AiFeatureCatalog::featureLabel($f->feature) }}</a>
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
                <th class="text-right">Coin balance</th>
                <th class="text-right">Spent</th>
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
                    <td class="text-right text-blue-200 font-semibold">{{ number_format((int) ($b->balance ?? 0)) }}</td>
                    <td class="text-right text-red-300">{{ number_format($r->spent) }}</td>
                    <td class="text-right text-blue-300">{{ number_format($r->refunded) }}</td>
                    <td class="text-right text-amber-300">{{ number_format($r->adjusted) }}</td>
                    <td class="text-right text-white/70">{{ number_format($r->calls) }}</td>
                    <td class="text-right text-white/50 text-xs">{{ number_format($r->tokens_in + $r->tokens_out) }}</td>
                    <td class="text-right">
                        @if($u)
                            <a href="{{ route('admin.ai-usage.show', $u) }}" class="text-blue-300 hover:underline text-xs">View / adjust</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
