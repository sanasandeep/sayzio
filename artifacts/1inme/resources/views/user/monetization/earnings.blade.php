@extends('user.layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-4 md:p-6">
    @include('user.monetization._nav')

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif

    {{-- Stat cards (Task #1209): rolling-window totals + MRR / churn / LTV. --}}
    @php
        $cards = [
            ['label' => 'Today',          'value' => '$' . number_format($today / 100, 2),         'icon' => 'fa-bolt',          'tint' => '#06b6d4'],
            ['label' => 'Last 7 days',    'value' => '$' . number_format($last7 / 100, 2),         'icon' => 'fa-calendar-week', 'tint' => '#8b5cf6'],
            ['label' => 'Last 30 days',   'value' => '$' . number_format($last30 / 100, 2),        'icon' => 'fa-calendar-day',  'tint' => '#a855f7'],
            ['label' => 'This month',     'value' => '$' . number_format($thisMonth / 100, 2),     'icon' => 'fa-receipt',       'tint' => '#ec4899'],
            ['label' => 'All-time gross', 'value' => '$' . number_format($allTime / 100, 2),       'icon' => 'fa-coins',         'tint' => '#10b981'],
            ['label' => 'Refunds issued', 'value' => '$' . number_format(abs($refundsAllTime) / 100, 2), 'icon' => 'fa-rotate-left', 'tint' => '#f59e0b'],
            ['label' => 'Active subs',    'value' => number_format($activeSubscribers),            'icon' => 'fa-user-group',    'tint' => '#3b82f6'],
            ['label' => 'MRR',            'value' => '$' . number_format($mrrCents / 100, 2),      'icon' => 'fa-chart-line',    'tint' => '#0ea5e9'],
            ['label' => '30-day churn',   'value' => $churn30 . '%',                               'icon' => 'fa-user-slash',    'tint' => '#ef4444'],
            ['label' => 'Avg LTV / fan',  'value' => '$' . number_format($ltvCents / 100, 2),      'icon' => 'fa-gem',           'tint' => '#14b8a6'],
        ];
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        @foreach($cards as $c)
            <div class="rounded-xl border p-4" style="border-color: var(--border-color); background: var(--bg-card);">
                <div class="flex items-center justify-between">
                    <span class="text-xs uppercase tracking-wider font-semibold" style="color: var(--text-faint);">{{ $c['label'] }}</span>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: {{ $c['tint'] }}1f; color: {{ $c['tint'] }};">
                        <i class="fas {{ $c['icon'] }}"></i>
                    </span>
                </div>
                <div class="mt-3 text-xl font-bold" style="color: var(--text-primary);">{{ $c['value'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Top-earning posts (last 90 days). --}}
    @if($topPosts->isNotEmpty())
        <div class="rounded-xl border p-4 mb-6" style="border-color: var(--border-color); background: var(--bg-card);">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold" style="color: var(--text-primary);">Top earning posts</h3>
                <span class="text-xs" style="color: var(--text-faint);">PPV unlocks + tips · last 90 days</span>
            </div>
            <ol class="space-y-2">
                @foreach($topPosts as $row)
                    <li class="flex items-center gap-3">
                        <span class="w-6 text-center text-xs font-bold" style="color: var(--text-faint);">{{ $loop->iteration }}</span>
                        @if($row['post']->image)
                            <img src="{{ $row['post']->image }}" alt="" class="w-10 h-10 object-cover rounded">
                        @else
                            <div class="w-10 h-10 rounded bg-gradient-to-br from-violet-400 to-fuchsia-500"></div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm truncate" style="color: var(--text-primary);">
                                {{ $row['post']->title ?: \Illuminate\Support\Str::limit((string) $row['post']->body, 70) }}
                            </p>
                        </div>
                        <span class="text-sm font-bold" style="color: #10b981;">
                            ${{ number_format($row['total'] / 100, 2) }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Source breakdown + sparkline --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @php
            $sources = [
                ['key' => 'sub', 'label' => 'Subscriptions', 'tint' => '#8b5cf6'],
                ['key' => 'ppv', 'label' => 'Pay-per-view',  'tint' => '#3b82f6'],
                ['key' => 'tip', 'label' => 'Tips',          'tint' => '#10b981'],
                ['key' => 'product', 'label' => 'Products',   'tint' => '#f59e0b'],
                ['key' => 'form', 'label' => 'Paid forms',  'tint' => '#06b6d4'],
            ];
            $totalPositive = max(1, ($bySource['sub'] ?? 0) + ($bySource['ppv'] ?? 0) + ($bySource['tip'] ?? 0) + ($bySource['product'] ?? 0) + ($bySource['form'] ?? 0));
        @endphp
        @foreach($sources as $s)
            @php
                $val = max(0, (int) ($bySource[$s['key']] ?? 0));
                $pct = round($val / $totalPositive * 100);
            @endphp
            <div class="rounded-xl border p-4" style="border-color: var(--border-color); background: var(--bg-card);">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full" style="background: {{ $s['tint'] }};"></span>
                    <span class="text-sm font-semibold" style="color: var(--text-secondary);">{{ $s['label'] }}</span>
                </div>
                <div class="text-xl font-bold mb-2" style="color: var(--text-primary);">${{ number_format($val / 100, 2) }}</div>
                <div class="w-full h-1.5 rounded-full" style="background: rgba(148,163,184,0.15);">
                    <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $s['tint'] }};"></div>
                </div>
                <div class="text-xs mt-2" style="color: var(--text-faint);">{{ $pct }}% of credits</div>
            </div>
        @endforeach
    </div>

    {{-- 12-week chart --}}
    @if(count($series))
        <div class="rounded-xl border p-4 mb-6" style="border-color: var(--border-color); background: var(--bg-card);">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold" style="color: var(--text-primary);">Last 12 weeks</h3>
                <span class="text-xs" style="color: var(--text-faint);">Gross credits, weekly</span>
            </div>
            @php $max = max(1, max(array_column($series, 'total'))); @endphp
            <div class="flex items-end gap-1.5 h-28">
                @foreach($series as $w)
                    <div class="flex-1 flex flex-col items-center gap-1 group cursor-default">
                        <div class="w-full rounded-t" style="height: {{ max(2, round($w['total'] / $max * 100)) }}%; background: linear-gradient(to top, #8b5cf6, #c084fc); min-height: 2px;"
                             title="${{ number_format($w['total'] / 100, 2) }} · {{ $w['label'] }}"></div>
                        <span class="text-[10px]" style="color: var(--text-faint);">{{ $w['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Recent activity --}}
    <div class="rounded-xl border" style="border-color: var(--border-color); background: var(--bg-card);">
        <div class="p-4 border-b flex items-center justify-between" style="border-color: var(--border-color);">
            <h3 class="font-semibold" style="color: var(--text-primary);">Recent activity</h3>
            <a href="{{ route('user.monetization.payments') }}" class="text-sm" style="color: #8b5cf6;">View all →</a>
        </div>
        <div class="divide-y" style="--tw-divide-opacity: 1; border-color: var(--border-color);">
            @forelse($events as $e)
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3 min-w-0">
                        @php
                            $iconMap = [
                                'sub' => ['fa-user-plus', '#8b5cf6'],
                                'ppv' => ['fa-lock-open', '#3b82f6'],
                                'tip' => ['fa-heart',     '#ec4899'],
                                'product' => ['fa-bag-shopping', '#f59e0b'],
                                'form' => ['fa-wpforms', '#06b6d4'],
                            ];
                            [$ic, $tint] = $iconMap[$e->source] ?? ['fa-receipt', '#64748b'];
                        @endphp
                        <span class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: {{ $tint }}1f; color: {{ $tint }};">
                            <i class="fas {{ $ic }}"></i>
                        </span>
                        <div class="min-w-0">
                            <div class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ $e->describeShort() }}</div>
                            <div class="text-xs" style="color: var(--text-faint);">
                                {{ optional($e->occurred_at)->diffForHumans() ?? '—' }}
                                @if($e->fan_user_id)
                                    · {{ optional($e->fan)->name ?? 'Fan #'.$e->fan_user_id }}
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="text-sm font-semibold" style="color: {{ $e->amount_cents >= 0 ? '#10b981' : '#ef4444' }};">
                            {{ $e->amount_cents >= 0 ? '+' : '−' }}${{ number_format(abs($e->amount_cents) / 100, 2) }}
                        </div>
                        <div class="text-[11px]" style="color: var(--text-faint);">{{ strtoupper($e->currency) }}</div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm" style="color: var(--text-faint);">
                    No activity yet. Once a fan subscribes, unlocks a post, or tips you, it'll show up here.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
