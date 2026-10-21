{{--
    The four attendee report sections, under the funnel on Overview.

    Self-contained on purpose: one @include puts this anywhere. If the
    report should later live on its own tab, that is a line moved, not
    a rebuild.

    Every number comes from EventAttendeeReport. Nothing is computed
    here, so nothing here can disagree with the tests.

    Expects `$link`.
--}}
@php
    $erReport = \App\Services\Events\EventAttendeeReport::for($link);
    $erSales  = \App\Services\Events\EventAttendeeReport::sales($link);
    $erWhen   = \App\Services\Events\EventAttendeeReport::arrivals($link);
    $erNet    = \App\Services\Events\EventAttendeeReport::networking($link);

    $erMoney = function (int $cents, string $currency): string {
        // Minor units, formatted plainly. No symbol lookup table: the
        // code beside the number is unambiguous and a wrong symbol is
        // worse than none.
        return $currency . ' ' . number_format($cents / 100, 2);
    };

    // Relative to the doors opening, which is what the buckets measure.
    $erOffset = function (int $minutes): string {
        if ($minutes < 0) {
            return abs($minutes) >= 60
                ? floor(abs($minutes) / 60) . 'h before'
                : abs($minutes) . 'm before';
        }
        if ($minutes === 0) {
            return 'First 30m';
        }

        return $minutes >= 60
            ? '+' . floor($minutes / 60) . 'h' . ($minutes % 60 ? ' ' . ($minutes % 60) . 'm' : '')
            : '+' . $minutes . 'm';
    };

    $erPeak = collect($erWhen)->max('count') ?: 1;
@endphp

@if($erReport['rows']->isNotEmpty() || $erNet['opted_in'] > 0)
@once
<style>
    .er-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:14px 0 0}
    .er-stat{background:var(--bg-glass);border:1px solid var(--border-glass);border-radius:12px;padding:12px 14px}
    .er-stat-value{font-size:22px;font-weight:750;letter-spacing:-.03em;font-variant-numeric:tabular-nums;color:var(--text-primary);line-height:1.1}
    .er-stat-label{font-size:11px;color:var(--text-muted);margin-top:3px;display:block}
    /* A figure that is not a measurement is drawn as absent, never as a
       zero. Same rule the funnel follows, so the two agree on screen as
       well as in the service. */
    .er-stat-value.is-absent{color:var(--text-muted);font-weight:650;font-size:17px}
    .er-table{width:100%;border-collapse:collapse;margin-top:12px;font-size:12.5px}
    .er-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);font-weight:700;padding:6px 10px;border-bottom:1px solid var(--border-glass)}
    .er-table td{padding:8px 10px;border-bottom:1px solid var(--border-glass);color:var(--text-primary)}
    .er-table tbody tr:last-child td{border-bottom:0}
    .er-num{text-align:right;font-variant-numeric:tabular-nums}
    .er-pill{display:inline-block;font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:999px;border:1px solid var(--border-glass);color:var(--text-muted)}
    .er-pill.is-arrived{color:#10b981;border-color:color-mix(in srgb,#10b981 35%,transparent)}
    .er-pill.is-missing{color:#f59e0b;border-color:color-mix(in srgb,#f59e0b 35%,transparent)}
    .er-bars{display:flex;flex-direction:column;gap:6px;margin-top:12px}
    .er-bar-row{display:grid;grid-template-columns:minmax(72px,auto) 1fr auto;gap:10px;align-items:center;font-size:11.5px;color:var(--text-muted)}
    .er-bar{height:8px;border-radius:999px;background:var(--bg-glass);overflow:hidden}
    .er-bar span{display:block;height:100%;border-radius:999px;background:#10b981}
    .er-section{margin-top:22px}
    .er-section h3{font-size:13px;font-weight:700;color:var(--text-primary);margin:0}
    .er-section p.er-sub{font-size:11.5px;color:var(--text-muted);margin:3px 0 0}
    .er-head-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .er-scroll{max-height:340px;overflow-y:auto}
</style>
@endonce

<section class="glass rounded-2xl px-5 py-4 mb-4" aria-labelledby="er-title">
    <div class="er-head-row">
        <div>
            <h2 id="er-title" class="text-base font-semibold" style="color:var(--text-primary)">Attendees</h2>
            <p class="er-sub">Who said they were coming, who turned up, what they paid, and what happened in the room.</p>
        </div>
        @if($erReport['rows']->isNotEmpty())
            <a href="{{ route('user.links.ics.report.csv', $link) }}" class="table-action">
                <i class="fas fa-download"></i> Export CSV
            </a>
        @endif
    </div>

    {{-- Who came vs who said they would ------------------------------ --}}
    <div class="er-grid">
        <div class="er-stat">
            <span class="er-stat-value">{{ number_format($erReport['expected']) }}</span>
            <span class="er-stat-label">Expected</span>
        </div>
        <div class="er-stat">
            <span class="er-stat-value">{{ number_format($erReport['arrived']) }}</span>
            <span class="er-stat-label">Arrived</span>
        </div>
        <div class="er-stat">
            @if($erReport['no_shows'] === null)
                {{-- Before the doors open nobody is a no-show, they are
                     early. A 0 here would be a false statement. --}}
                <span class="er-stat-value is-absent">Not yet</span>
                <span class="er-stat-label">No-shows once it starts</span>
            @else
                <span class="er-stat-value">{{ number_format($erReport['no_shows']) }}</span>
                <span class="er-stat-label">Didn't turn up</span>
            @endif
        </div>
        <div class="er-stat">
            <span class="er-stat-value">{{ number_format($erReport['heads']) }}</span>
            <span class="er-stat-label">People, counting guests</span>
        </div>
        @if($erReport['released'] > 0)
        <div class="er-stat">
            <span class="er-stat-value">{{ number_format($erReport['released']) }}</span>
            <span class="er-stat-label">Cancelled or refunded</span>
        </div>
        @endif
    </div>

    @if($erReport['rows']->isNotEmpty())
    <div class="er-scroll">
        <table class="er-table">
            <thead>
                <tr>
                    <th>Name</th><th>Via</th><th class="er-num">Guests</th><th>Status</th><th>Arrived</th>
                </tr>
            </thead>
            <tbody>
                @foreach($erReport['rows'] as $erRow)
                <tr>
                    <td>
                        {{ $erRow['name'] }}
                        @if($erRow['email'])<br><span style="font-size:11px;color:var(--text-muted)">{{ $erRow['email'] }}</span>@endif
                    </td>
                    <td>{{ $erRow['via'] }}</td>
                    <td class="er-num">{{ $erRow['guests'] ?: '' }}</td>
                    <td>
                        <span class="er-pill {{ $erRow['status'] === 'arrived' ? 'is-arrived' : ($erRow['status'] === 'no_show' ? 'is-missing' : '') }}">
                            {{ str_replace('_', ' ', $erRow['status']) }}
                        </span>
                    </td>
                    <td>{{ $erRow['arrived_at']?->format('H:i') ?: '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- What was sold ------------------------------------------------- --}}
    @if($erSales['tiers'])
    <div class="er-section">
        <h3>Tickets sold</h3>
        <p class="er-sub">What was charged at the time, not what the tier costs now.</p>
        <table class="er-table">
            <thead>
                <tr><th>Tier</th><th class="er-num">Orders</th><th class="er-num">Seats</th><th class="er-num">Taken</th></tr>
            </thead>
            <tbody>
                @foreach($erSales['tiers'] as $erTier)
                <tr>
                    <td>{{ $erTier['tier'] }}</td>
                    <td class="er-num">{{ number_format($erTier['orders']) }}</td>
                    <td class="er-num">{{ number_format($erTier['seats']) }}</td>
                    <td class="er-num">{{ $erMoney($erTier['cents'], $erTier['currency']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="er-grid">
            @foreach($erSales['takings'] as $erCur => $erCents)
            <div class="er-stat">
                <span class="er-stat-value">{{ $erMoney($erCents, $erCur) }}</span>
                <span class="er-stat-label">Taken</span>
            </div>
            @endforeach
            @foreach($erSales['refunded'] as $erCur => $erCents)
            <div class="er-stat">
                <span class="er-stat-value">{{ $erMoney($erCents, $erCur) }}</span>
                <span class="er-stat-label">Refunded</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- When people walked in ------------------------------------------ --}}
    @if($erWhen)
    <div class="er-section">
        <h3>When people arrived</h3>
        <p class="er-sub">Half hours, measured from the doors opening.</p>
        <div class="er-bars">
            @foreach($erWhen as $erBucket)
            <div class="er-bar-row">
                <span>{{ $erOffset($erBucket['offset_minutes']) }}</span>
                <span class="er-bar"><span style="width: {{ max(4, round($erBucket['count'] / $erPeak * 100)) }}%"></span></span>
                <span class="er-num">{{ number_format($erBucket['count']) }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Networking ------------------------------------------------------ --}}
    @if($erNet['opted_in'] > 0 || $erNet['requested'] > 0)
    <div class="er-section">
        <h3>In the room</h3>
        <p class="er-sub">Attendees who turned on "Who else is here", and the contact swaps that followed.</p>
        <div class="er-grid">
            <div class="er-stat">
                <span class="er-stat-value">{{ number_format($erNet['opted_in']) }}</span>
                <span class="er-stat-label">Opted in</span>
            </div>
            <div class="er-stat">
                <span class="er-stat-value">{{ number_format($erNet['requested']) }}</span>
                <span class="er-stat-label">Swaps asked for</span>
            </div>
            <div class="er-stat">
                <span class="er-stat-value">{{ number_format($erNet['accepted']) }}</span>
                <span class="er-stat-label">Accepted</span>
            </div>
            <div class="er-stat">
                @if($erNet['rate'] === null)
                    {{-- Nobody asked. 0% would read as everybody refusing. --}}
                    <span class="er-stat-value is-absent">Nobody asked</span>
                    <span class="er-stat-label">Acceptance</span>
                @else
                    <span class="er-stat-value">{{ round($erNet['rate'] * 100) }}%</span>
                    <span class="er-stat-label">Accepted of asked</span>
                @endif
            </div>
        </div>
    </div>
    @endif
</section>
@endif
