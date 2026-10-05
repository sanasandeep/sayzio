{{--
    The four numbers at the top of an orders board.

    Sana, 2026-10-05: "orders dashbord summary missing".

    Every one of them was computable from rows already on the page and
    shown nowhere. The owner opening this on a Sunday night wants how many,
    how much, the average ticket, and how many are still open -- then the
    list, which is what the kitchen reads.

    ---- Over the range, not over the page --------------------------------

    MenuOrderSummary aggregates the SCOPED QUERY, so these do not change
    when somebody taps "load more". A total that moves while you look at it
    teaches people not to trust the screen.

    Parameters:
      $osSummary   from MenuOrderSummary::of()
      $osLabels    status => human label
      $osCurrency  the menu's currency code
      $osExport    base URL for the export, filters already attached
      $osKitchen   the kitchen board for this page, or null
      $osRange     the resolved range, for the label
--}}
<div class="os-wrap">
    <div class="os-tiles">
        <div class="os-tile">
            <div class="os-k">Orders</div>
            <div class="os-v">{{ number_format($osSummary['orders']) }}</div>
            <div class="os-s">{{ $osRange['label'] ?? 'All time' }}</div>
        </div>
        <div class="os-tile">
            <div class="os-k">Revenue</div>
            <div class="os-v">{{ $osCurrency }} {{ number_format($osSummary['revenue'], 2) }}</div>
            {{-- Said on the tile, because a revenue figure that quietly
                 included cancellations would be wrong in the direction
                 nobody checks. --}}
            <div class="os-s">Cancelled excluded</div>
        </div>
        <div class="os-tile">
            <div class="os-k">Average</div>
            <div class="os-v">{{ $osCurrency }} {{ number_format($osSummary['average'], 2) }}</div>
            <div class="os-s">Per order</div>
        </div>
        <div class="os-tile">
            <div class="os-k">Still open</div>
            <div class="os-v">{{ number_format($osSummary['open']) }}</div>
            <div class="os-s">Needs attention</div>
        </div>
    </div>

    <div class="os-foot">
        <div class="os-statuses">
            @foreach($osSummary['by_status'] as $osStatus => $osRow)
                @if($osRow['count'] > 0)
                    <span class="os-chip"><b>{{ number_format($osRow['count']) }}</b> {{ strtolower($osLabels[$osStatus] ?? $osStatus) }}</span>
                @endif
            @endforeach
        </div>
        <div class="os-export">
            {{-- Sana, 2026-10-05: "Need another dashboard like kitchen".
                 It lives here because this is the one row both order boards
                 share, so the restaurant and the store get the link from
                 one edit rather than two that drift. --}}
            @if($osKitchen ?? null)
                <a class="os-btn os-btn-go" href="{{ $osKitchen }}"><i class="fas fa-fire-burner"></i> Kitchen</a>
            @endif
            {{-- The filters are already on these URLs. An export that
                 quietly ignores the range is worse than no export: you
                 filter to last month, download, and reconcile a year. --}}
            <a class="os-btn" href="{{ $osExport }}&format=csv"><i class="fas fa-file-csv"></i> CSV</a>
            <a class="os-btn" href="{{ $osExport }}&format=pdf"><i class="fas fa-file-pdf"></i> PDF</a>
        </div>
    </div>
</div>

@once
<style>
    .os-wrap {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        padding: 16px 18px;
        margin-bottom: 14px;
    }
    .os-tiles {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    @media (max-width: 720px) {
        .os-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .os-tile {
        border: 1px solid var(--border-glass);
        border-radius: 12px;
        padding: 10px 12px;
        min-width: 0;
    }
    .os-k {
        font-size: 10.5px; text-transform: uppercase; letter-spacing: .06em;
        color: var(--text-faint);
    }
    .os-v {
        font-size: 20px; font-weight: 800; margin-top: 2px;
        color: var(--text-primary);
        /* Figures in a row line up, so four tiles read as one set. */
        font-variant-numeric: tabular-nums;
        overflow-wrap: anywhere;
    }
    .os-s { font-size: 10.5px; color: var(--text-dimmed); margin-top: 1px; }
    .os-foot {
        display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
        margin-top: 12px;
    }
    .os-statuses { display: flex; gap: 8px; flex-wrap: wrap; flex: 1; min-width: 0; }
    .os-chip {
        font-size: 11.5px; color: var(--text-muted);
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 999px;
        padding: 3px 10px;
        white-space: nowrap;
    }
    .os-chip b { color: var(--text-primary); }
    .os-export { display: flex; gap: 8px; margin-left: auto; }
    .os-btn {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 12px; font-weight: 600;
        color: var(--text-muted);
        border: 1px solid var(--border-glass);
        border-radius: 999px;
        padding: 6px 13px;
        text-decoration: none;
        white-space: nowrap;
    }
    .os-btn:hover { color: var(--text-primary); }
    /* The kitchen screen is a place to GO, not a file to take away, so it
       does not read as a third download button sitting beside two. */
    .os-btn-go {
        color: #fb923c;
        border-color: rgba(251,146,60,.35);
        background: rgba(251,146,60,.07);
    }
    .os-btn-go:hover { color: #fdba74; }
</style>
@endonce
