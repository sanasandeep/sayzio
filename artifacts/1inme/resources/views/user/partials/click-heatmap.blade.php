{{--
    One click heatmap, drawn the same way everywhere.

    It was two: the dashboard tinted the user's accent colour by alpha, the
    link stats page had its own six-step ramp, and neither agreed with the
    other on cell size, row labels or what happened on hover. Sana asked for
    "better and similar look for both places", so there is now one partial and
    both pages include it.

    ---- Why the steps are on a LOG scale -------------------------------
    The colours looked "off" because of the values, not the palette. Sayzio
    click traffic is extremely peaky: one hour of a launch can carry 465
    clicks while a normal busy hour carries 40. Binning on the square root of
    the share (what both pages did before) is still dominated by that one
    outlier, so almost every block landed on step 1 or 2 and the top of the
    ramp only ever appeared on the single peak cell:

        clicks    sqrt    log
             1       1      1
            20       2      3
            35       2      4
            60       3      5
           140       4      5
           220       5      6
           465       6      6

    Log binning spreads a skewed week across all six steps while keeping the
    plain meaning intact: more clicks is always further up the ramp, and equal
    counts are always the same colour.

    ---- Parameters -----------------------------------------------------
    $hmRows      list of rows, each:
                   day    'Tue'          the row label
                   date   '22' | null    optional date, shown after the label
                   today  bool           marks the last row on the dashboard
                   full   'Tuesday'      how a row reads in a native tooltip
                   blocks [12 ints]      clicks per two-hour block, 00 -> 22
    $hmPeak      int, the largest count in $hmRows
    $hmPeakLabel string, where that peak is ('Tue 12:00', 'Tue 22 Sep · 14:00')
    $hmId        unique id prefix for this instance
--}}
@php
    $hmId = $hmId ?? 'hm';
    // Small HTML, echoed raw below: the <b> has to survive into the
    // attribute (the script reads it back and writes it as innerHTML) and so
    // does the &middot;, which {{ }} would turn into a literal "&middot;".
    // Only the label can vary, and it is escaped before it goes in.
    $hmIdleHtml = 'Peak <b>'.number_format($hmPeak).'</b> '.\Illuminate\Support\Str::plural('click', $hmPeak)
        .' &middot; '.e($hmPeakLabel);
    // The divisor for the log scale. max(2, …) keeps log(1 + max) away from
    // zero when the whole week is a single click.
    $hmSpan = log(1 + max(2, (int) $hmPeak));
@endphp

@once
@push('styles')
<style>
/* ---- The heat ramp -------------------------------------------------
   Six steps, light -> dark, blue -> indigo -> violet -> plum. It is
   deliberately NOT one hue: Sana asked for colour, and a ramp that also
   turns is easier to read a value off than six tints of the same blue.
   What keeps it honest is that LIGHTNESS still moves in one direction
   (checked: monotone in OKLab L, every adjacent gap >= 0.07), so the order
   survives for a colour-blind reader and in greyscale -- the thing a
   rainbow ramp breaks. Step 0 is allowed to recede into the card, which is
   how "no clicks at all" should read. */
.hm-wrap {
    --hl0: #eef1f8;
    --hl1: #dde7fb;
    --hl2: #aec6f6;
    --hl3: #8093ee;
    --hl4: #6f63e4;
    --hl5: #7b34cf;
    --hl6: #6a0f6f;
    --hl-ink: #1a1025;
    --hm-label: 46px;
    --hm-gap: 4px;
}
html:not(.light-mode) .hm-wrap {
    --hl0: rgba(255,255,255,0.05);
    --hl1: #16224a;
    --hl2: #27418f;
    --hl3: #4d62dd;
    --hl4: #8a6cf6;
    --hl5: #cf86f2;
    --hl6: #f7cdf8;
    --hl-ink: #ffffff;
}

/* The grid and the hour strip share one column template, or the labels
   drift out from under the blocks they name. Capped and centred so the
   cells stay square in a full-width card as well as in a dashboard
   panel -- 1fr tracks alone gave 100px blocks on a wide screen, which
   stops looking like a heatmap and starts looking like a wall. */
.hm, .hm-hours {
    display: grid;
    grid-template-columns: var(--hm-label) repeat(12, minmax(0, 1fr));
    gap: var(--hm-gap);
    max-width: 540px;
    margin-inline: auto;
}
.hm { align-items: center; }
.hm-day {
    font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 9.5px;
    color: var(--text-faint);
    white-space: nowrap;
    /* Right-aligned so every row sits the same distance from its first
       block, whether the label is "Mon" or "Wed 16". */
    text-align: right;
    padding-right: 3px;
}
.hm-day em { font-style: normal; color: var(--text-muted); font-variant-numeric: tabular-nums; }
.hm-day.is-today, .hm-day.is-today em { color: var(--text-primary); }

.hm-cell {
    aspect-ratio: 1 / 1;
    max-height: 34px;
    border-radius: 6px;
    background: var(--hl0);
    transition: transform .12s ease, box-shadow .12s ease;
}
.hm-cell[data-level="1"] { background: var(--hl1); }
.hm-cell[data-level="2"] { background: var(--hl2); }
.hm-cell[data-level="3"] { background: var(--hl3); }
.hm-cell[data-level="4"] { background: var(--hl4); }
.hm-cell[data-level="5"] { background: var(--hl5); }
.hm-cell[data-level="6"] { background: var(--hl6); }
/* Hover/tap: lift the block and ring it in the page ink, so the one being
   read out is unmistakable against its neighbours. */
.hm-cell:hover, .hm-cell.is-on {
    transform: scale(1.18);
    z-index: 2;
    box-shadow: 0 0 0 2px var(--bg-card), 0 0 0 3.5px var(--hl-ink), 0 6px 16px -6px rgba(0,0,0,.5);
}

/* The value itself, above the grid, replaced by whatever is hovered. The
   reserved height stops the card jumping as the text changes. */
.hm-readout {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    justify-content: center; min-height: 22px; margin: 0 0 12px;
    font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 12px; color: var(--text-muted); text-align: center;
}
.hm-readout b { color: var(--text-primary); font-weight: 600; font-size: 14px; }
.hm-hint { font-size: 10px; letter-spacing: .08em; text-transform: uppercase; color: var(--text-faint); }

.hm-hours {
    margin-top: 6px;
    font-family: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 9px;
    color: var(--text-faint);
    text-align: center;
}
.hm-scale {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-top: 14px;
    font-size: 10px; font-weight: 600; letter-spacing: .12em;
    text-transform: uppercase; color: var(--text-faint);
}
.hm-steps { display: inline-flex; gap: 3px; }
.hm-steps i { width: 13px; height: 13px; border-radius: 4px; display: inline-block; }

@media (max-width: 560px) {
    .hm-wrap { --hm-label: 38px; --hm-gap: 3px; }
    .hm-cell { border-radius: 4px; }
    .hm-hours { font-size: 8px; }
    .hm-day { font-size: 8.5px; }
    .hm-steps i { width: 11px; height: 11px; }
}
@media (prefers-reduced-motion: reduce) {
    .hm-cell { transition: none; }
    .hm-cell:hover, .hm-cell.is-on { transform: none; }
}
</style>
@endpush
@endonce

<div class="hm-wrap" id="{{ $hmId }}-wrap">
    {{-- The value, spelled out. The grid on its own can only say "darker than
         that one"; this says 457. It follows the cursor, and a tap does the
         same thing on a phone. --}}
    <p class="hm-readout" id="{{ $hmId }}-readout" data-idle="{!! $hmIdleHtml !!}">
        {!! $hmIdleHtml !!}
        <span class="hm-hint">hover a block</span>
    </p>

    <div class="hm" id="{{ $hmId }}-grid" role="img"
         aria-label="Click density by day and hour. Busiest at {{ $hmPeakLabel }} with {{ number_format($hmPeak) }} clicks.">
        @foreach($hmRows as $row)
            <span class="hm-day{{ ($row['today'] ?? false) ? ' is-today' : '' }}">{{ $row['day'] }}@if(!empty($row['date'])) <em>{{ $row['date'] }}</em>@endif</span>
            @foreach($row['blocks'] as $b => $n)
                @php
                    $n = (int) $n;
                    // Six log steps of the peak. Anything above zero is at
                    // least step 1, so a single click is never invisible.
                    $level = $n > 0 ? max(1, min(6, (int) ceil(log(1 + $n) / $hmSpan * 6))) : 0;
                    $from  = str_pad((string) ($b * 2), 2, '0', STR_PAD_LEFT);
                    $to    = str_pad((string) ((($b * 2) + 2) % 24), 2, '0', STR_PAD_LEFT);
                    $when  = trim($row['day'].' '.($row['date'] ?? '')).' '.$from.':00–'.$to.':00';
                    $unit  = \Illuminate\Support\Str::plural('click', $n);
                @endphp
                <span class="hm-cell" data-level="{{ $level }}"
                      data-when="{{ $when }}"
                      data-count="{{ number_format($n) }}"
                      data-unit="{{ $unit }}"
                      title="{{ $row['full'] }} {{ $from }}:00–{{ $to }}:00 &middot; {{ number_format($n) }} {{ $unit }}"></span>
            @endforeach
        @endforeach
    </div>

    <div class="hm-hours">
        {{-- One spacer for the day column, then twelve labels for twelve
             cells. The list must not lead with a blank as well, or every
             label sits one two-hour block to the right of what it names. --}}
        <span></span>
        @foreach(['00','','04','','08','','12','','16','','20',''] as $h)<span>{{ $h }}</span>@endforeach
    </div>

    <div class="hm-scale">
        <span>Quiet</span>
        <span class="hm-steps">
            @foreach([1, 2, 3, 4, 5, 6] as $step)
                <i style="background: var(--hl{{ $step }})"></i>
            @endforeach
        </span>
        <span>Busy</span>
    </div>
</div>

@once
@push('scripts')
<script>
// Reads a block out when it is hovered or tapped. Written once and bound to
// every .hm-wrap on the page, so a second heatmap costs no extra script.
(function () {
    document.querySelectorAll('.hm-wrap').forEach(function (wrap) {
        var grid = wrap.querySelector('.hm');
        var out  = wrap.querySelector('.hm-readout');
        if (!grid || !out) return;
        var idle = out.getAttribute('data-idle');
        var on   = null;

        function show(cell) {
            if (on && on !== cell) on.classList.remove('is-on');
            on = cell;
            cell.classList.add('is-on');
            out.innerHTML = cell.dataset.when + ' &middot; <b>' + cell.dataset.count + '</b> ' + cell.dataset.unit;
        }
        function clear() {
            if (on) { on.classList.remove('is-on'); on = null; }
            out.innerHTML = idle + ' <span class="hm-hint">hover a block</span>';
        }

        grid.addEventListener('mouseover', function (e) {
            var cell = e.target.closest('.hm-cell');
            if (cell) show(cell);
        });
        grid.addEventListener('mouseleave', clear);
        // Touch: a tap reads the block out and keeps it marked until the next
        // tap, because there is no hover to fall back on.
        grid.addEventListener('click', function (e) {
            var cell = e.target.closest('.hm-cell');
            if (!cell) return;
            if (cell === on) { clear(); } else { show(cell); }
        });
    });
})();
</script>
@endpush
@endonce
