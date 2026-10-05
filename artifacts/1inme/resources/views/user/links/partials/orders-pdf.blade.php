{{--
    The orders of a range, as a document somebody can file.

    Sana, 2026-10-05: "export of orders , pdf, csv, with filter options
    active".

    ---- Written for paper, not for a screen -------------------------------

    No colour that only works on a monitor, no font this renderer has to
    fetch, and the summary first: a printed report is read by somebody who
    wants the four numbers and only then the rows. dompdf has no network
    here on purpose -- a report that silently loses its styling because a
    CDN was slow is not a report.

    ---- It says what it is not showing -----------------------------------

    If the range holds more orders than the cap, that is stated at the top
    in the same weight as the totals. A document whose last page is missing
    and does not admit it is how somebody under-declares their takings.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        .sub { font-size: 10px; color: #555; margin: 0 0 14px; }
        .tiles { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .tiles td {
            border: 1px solid #ddd; padding: 8px 10px; width: 25%;
            vertical-align: top;
        }
        .tiles .k { font-size: 8.5px; text-transform: uppercase; letter-spacing: .05em; color: #666; }
        .tiles .v { font-size: 15px; font-weight: bold; margin-top: 2px; }
        .warn {
            border: 1px solid #c88; background: #fdf3f3; color: #8a3b3b;
            padding: 7px 10px; margin-bottom: 12px; font-size: 10px;
        }
        .statuses { font-size: 9.5px; color: #444; margin: 0 0 14px; }
        .statuses span { margin-right: 14px; white-space: nowrap; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th {
            text-align: left; font-size: 8.5px; text-transform: uppercase;
            letter-spacing: .04em; color: #555; border-bottom: 1.5px solid #999;
            padding: 5px 4px;
        }
        table.rows td { border-bottom: 1px solid #e6e6e6; padding: 5px 4px; vertical-align: top; }
        td.num { text-align: right; white-space: nowrap; }
        .lines { color: #555; font-size: 9px; }
        .foot { margin-top: 14px; font-size: 8.5px; color: #777; }
    </style>
</head>
<body>

<h1>{{ $link->title ?: $link->alias }}: orders</h1>
<p class="sub">
    {{ $range['label'] ?? 'All time' }}
    · generated {{ now()->format('j M Y, H:i') }}
</p>

@if($truncated > 0)
    <div class="warn">
        <b>This document shows the most recent {{ number_format($cap) }} of {{ number_format($summary['orders']) }}.</b>
        {{ number_format($truncated) }} older {{ $truncated === 1 ? 'order is' : 'orders are' }} not listed below.
        the totals above them count every one. Narrow the dates, or use the CSV, for the full list.
    </div>
@endif

<table class="tiles">
    <tr>
        <td>
            <div class="k">Orders</div>
            <div class="v">{{ number_format($summary['orders']) }}</div>
        </td>
        <td>
            <div class="k">Revenue</div>
            <div class="v">{{ $menu->currency }} {{ number_format($summary['revenue'], 2) }}</div>
        </td>
        <td>
            <div class="k">Average</div>
            <div class="v">{{ $menu->currency }} {{ number_format($summary['average'], 2) }}</div>
        </td>
        <td>
            <div class="k">Still open</div>
            <div class="v">{{ number_format($summary['open']) }}</div>
        </td>
    </tr>
</table>

<p class="statuses">
    @foreach($summary['by_status'] as $status => $row)
        <span><b>{{ $labels[$status] ?? $status }}</b> {{ number_format($row['count']) }}</span>
    @endforeach
    <br>
    <span style="color:#777">Revenue and average exclude cancelled orders.</span>
</p>

<table class="rows">
    <thead>
        <tr>
            <th style="width:8%">Order</th>
            <th style="width:16%">Placed</th>
            <th style="width:12%">Status</th>
            <th style="width:18%">Customer</th>
            <th>Items</th>
            <th class="num" style="width:13%">Total</th>
        </tr>
    </thead>
    <tbody>
    @forelse($orders as $order)
        <tr>
            <td>{{ $order->token_number ?: $order->id }}</td>
            <td>{{ optional($order->created_at)->format('j M, H:i') }}</td>
            <td>{{ $labels[$order->status] ?? $order->status }}</td>
            <td>
                {{ $order->customer_name ?: '—' }}
                @if($order->table_label ?? null)
                    <div class="lines">{{ $order->table_label }}</div>
                @endif
            </td>
            <td class="lines">
                @foreach($order->items as $item)
                    {{ $item->quantity }}× {{ $item->name }}@if(! $loop->last); @endif
                @endforeach
            </td>
            <td class="num">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="6" style="padding:14px 4px;color:#777">No orders in this range.</td></tr>
    @endforelse
    </tbody>
</table>

<p class="foot">
    Estimated bills, not final bills: the same figures the orders board shows for this range.
</p>

</body>
</html>
