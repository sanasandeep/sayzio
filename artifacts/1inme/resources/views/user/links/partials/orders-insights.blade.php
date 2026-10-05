{{--
    What the orders are telling the owner, rather than how many there were.

    Sana, 2026-10-05: "dashborad: i need top items, item sales, reccuring
    things, highlights or anything related....".

    The tiles above this say how much came in. They cannot say what to DO.
    These four can: what sells, what earns, who comes back, and what has
    never sold at all.

    Every number here excludes cancelled orders. A dish ordered twice and
    cancelled twice is not a best seller, and putting it at the top of this
    list would be this screen's fault.

    Parameters:
      $oiData      from MenuInsights::of()
      $oiCurrency  the menu's currency code
      $oiNoun      "item" or "product"
      $oiRange     the resolved range, for the label
--}}
@php
    $oiMoney = fn ($n) => $oiCurrency.' '.number_format((float) $n, 2);
    $oiPeak  = $oiData['busiest'][0] ?? null;
@endphp

<div class="oi-wrap">
    <div class="oi-head">
        <h2 class="oi-h">What is selling</h2>
        <span class="oi-range">{{ $oiRange['label'] ?? 'All time' }} · cancelled excluded</span>
    </div>

    @if($oiData['sold'] === 0)
        <p class="oi-none">Nothing has sold in this range yet. Pick a wider one, or come back after service.</p>
    @else
        {{-- The highlights line: the three things worth knowing before any
             list. Written as a sentence because that is how somebody would
             say it, and a sentence survives being read at a glance. --}}
        <div class="oi-highlights">
            <span class="oi-hl">
                <b>{{ number_format($oiData['sold']) }}</b> {{ Str::plural($oiNoun, $oiData['sold']) }} sold
                across <b>{{ number_format($oiData['distinct']) }}</b> different ones
            </span>
            @if($oiPeak)
                <span class="oi-hl">busiest around <b>{{ $oiPeak['label'] }}</b></span>
            @endif
            @if($oiData['regulars']['count'] > 0)
                <span class="oi-hl"><b>{{ number_format($oiData['regulars']['count']) }}</b> {{ Str::plural('regular', $oiData['regulars']['count']) }}</span>
            @endif
        </div>

        <div class="oi-grid">
            {{-- Two lists, because they are two different questions. A
                 30-rupee tea outsells everything on the menu and earns less
                 than the dish nobody reorders; showing only one list hides
                 whichever answer the owner needed. --}}
            <div class="oi-col">
                <div class="oi-k">Most ordered</div>
                <ol class="oi-list">
                    @foreach($oiData['top_qty'] as $row)
                        <li>
                            <span class="oi-name">{{ $row['name'] }}</span>
                            <span class="oi-v">{{ number_format($row['qty']) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="oi-col">
                <div class="oi-k">Earns the most</div>
                <ol class="oi-list">
                    @foreach($oiData['top_money'] as $row)
                        <li>
                            <span class="oi-name">{{ $row['name'] }}</span>
                            <span class="oi-v">{{ $oiMoney($row['revenue']) }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="oi-col">
                <div class="oi-k">Coming back</div>
                @if($oiData['regulars']['rows'])
                    <ol class="oi-list">
                        @foreach($oiData['regulars']['rows'] as $row)
                            <li>
                                <span class="oi-name">
                                    {{ $row['name'] }}
                                    @if($row['last'])<em class="oi-when">{{ $row['last'] }}</em>@endif
                                </span>
                                <span class="oi-v">{{ number_format($row['orders']) }}×</span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    {{-- Said plainly rather than left blank. "No regulars yet"
                         is information; an empty column reads as broken. --}}
                    <p class="oi-empty">Nobody has ordered twice yet in this range.</p>
                @endif
            </div>

            <div class="oi-col">
                <div class="oi-k">Never ordered</div>
                @if($oiData['never'])
                    <ul class="oi-list oi-plain">
                        @foreach($oiData['never'] as $name)
                            <li><span class="oi-name oi-dim">{{ $name }}</span></li>
                        @endforeach
                    </ul>
                    {{-- The point of the column, said once. --}}
                    <p class="oi-note">On the menu, nobody has ordered them in this range.</p>
                @else
                    <p class="oi-empty">Everything on the menu has sold at least once.</p>
                @endif
            </div>
        </div>
    @endif
</div>

@once
<style>
    .oi-wrap {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        padding: 16px 18px;
        margin-bottom: 14px;
    }
    .oi-head { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
    .oi-h { font-size: 14px; font-weight: 700; color: var(--text-primary); }
    .oi-range { font-size: 11px; color: var(--text-faint); margin-left: auto; }
    .oi-none, .oi-empty { font-size: 12.5px; color: var(--text-dimmed); }

    .oi-highlights {
        display: flex; gap: 8px; flex-wrap: wrap;
        margin-bottom: 14px;
    }
    .oi-hl {
        font-size: 12px; color: var(--text-muted);
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 999px;
        padding: 4px 11px;
    }
    .oi-hl b { color: var(--text-primary); font-variant-numeric: tabular-nums; }

    .oi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 18px;
    }
    .oi-col { min-width: 0; }
    .oi-k {
        font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em;
        color: var(--text-faint); margin-bottom: 7px;
    }
    .oi-list { margin: 0; padding: 0; list-style: none; counter-reset: oi; }
    .oi-list li {
        display: flex; align-items: baseline; gap: 10px;
        font-size: 13px; padding: 3px 0;
        color: var(--text-primary);
        min-width: 0;
    }
    /* Numbered, because these are rankings and the order IS the
       information. The plain list is not — nothing is first. */
    .oi-list:not(.oi-plain) li::before {
        counter-increment: oi;
        content: counter(oi);
        font-size: 10.5px; color: var(--text-faint);
        min-width: 1.1em; flex: none;
        font-variant-numeric: tabular-nums;
    }
    .oi-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
    .oi-dim { color: var(--text-muted); }
    .oi-when { display: block; font-size: 11px; color: var(--text-faint); font-style: normal; }
    .oi-v {
        flex: none; font-weight: 700; font-variant-numeric: tabular-nums;
        color: var(--text-primary); white-space: nowrap;
    }
    .oi-note { font-size: 11px; color: var(--text-faint); margin-top: 7px; }
</style>
@endonce
