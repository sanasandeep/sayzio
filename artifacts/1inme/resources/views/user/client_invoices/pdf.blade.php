@php
    $cur   = strtoupper($invoice->currency ?: 'USD');
    $money = fn ($m) => $cur . ' ' . number_format(((int) $m) / 100, 2);
    $lines = is_array($invoice->line_items) ? $invoice->line_items : [];
    $taxRows = is_array($invoice->tax_breakdown) ? $invoice->tax_breakdown : [];
    $status  = strtolower((string) $invoice->status);
    $isPaid  = $status === 'paid';
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 32px 36px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2430; font-size: 11px; line-height: 1.5; }
        .head { width: 100%; margin-bottom: 24px; }
        .head td { vertical-align: top; }
        .logo { max-height: 56px; max-width: 200px; margin-bottom: 6px; }
        .brand-name { font-size: 17px; font-weight: bold; color: #111827; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 10px; }
        .doc-title { font-size: 22px; font-weight: bold; color: #6d28d9; letter-spacing: 0.5px; margin: 0; text-align: right; }
        .doc-meta { text-align: right; margin-top: 4px; }
        .doc-meta div { margin: 1px 0; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 10px; font-weight: bold; letter-spacing: 0.4px; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-due  { background: #fef3c7; color: #92400e; }
        .section-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; color: #9ca3af; margin: 0 0 4px; }
        .parties { width: 100%; margin: 18px 0 20px; }
        .parties td { vertical-align: top; width: 50%; padding-right: 16px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { text-align: left; background: #f5f3ff; color: #5b21b6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px; border-bottom: 2px solid #ede9fe; }
        table.items td { padding: 8px; border-bottom: 1px solid #eef0f4; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 46%; float: right; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 2px solid #1f2430; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .notes { clear: both; margin-top: 32px; padding-top: 14px; border-top: 1px solid #eef0f4; }
        .foot { margin-top: 36px; text-align: center; color: #9ca3af; font-size: 9px; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 58%;">
                @if(!empty($brand['logo_data_uri']))
                    <img class="logo" src="{{ $brand['logo_data_uri'] }}" alt="">
                @endif
                <p class="brand-name">{{ $brand['name'] }}</p>
                @if(!empty($brand['legal_name']) && $brand['legal_name'] !== $brand['name'])
                    <div class="muted">{{ $brand['legal_name'] }}</div>
                @endif
                @foreach($brand['address_lines'] as $line)
                    <div class="muted">{{ $line }}</div>
                @endforeach
                @if(!empty($brand['email']))<div class="muted">{{ $brand['email'] }}</div>@endif
                @if(!empty($brand['phone']))<div class="muted">{{ $brand['phone'] }}</div>@endif
                @if(!empty($brand['website']))<div class="muted">{{ $brand['website'] }}</div>@endif
                @foreach($brand['tax_ids'] as $tid)
                    <div class="muted">{{ $tid }}</div>
                @endforeach
            </td>
            <td style="width: 42%;">
                <p class="doc-title">INVOICE</p>
                <div class="doc-meta">
                    <div><strong>{{ $invoice->number }}</strong></div>
                    <div class="muted">Issued {{ optional($invoice->issued_at)->format('d M Y') }}</div>
                    @if($invoice->due_date)
                        <div class="muted">Due {{ optional($invoice->due_date)->format('d M Y') }}</div>
                    @endif
                    <div style="margin-top: 6px;">
                        @if($isPaid)
                            <span class="badge badge-paid">PAID</span>
                        @else
                            <span class="badge badge-due">{{ strtoupper($invoice->status ?: 'DRAFT') }}</span>
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <p class="section-label">Billed to</p>
                <div>{{ $invoice->recipient_email ?: '—' }}</div>
            </td>
            <td class="right">
                <p class="section-label">Amount {{ $isPaid ? 'paid' : 'due' }}</p>
                <div style="font-size: 16px; font-weight: bold; color: #111827;">{{ $money($invoice->grand_total_minor) }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Qty</th>
                <th class="right">Unit</th>
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lines as $li)
                @php $qty = (int) ($li['quantity'] ?? 1); $unit = (int) ($li['amount_minor'] ?? 0); @endphp
                <tr>
                    <td>{{ $li['label'] ?? ($li['description'] ?? '') }}</td>
                    <td class="right">{{ $qty }}</td>
                    <td class="right">{{ $money($unit) }}</td>
                    <td class="right">{{ $money($unit * $qty) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No line items.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="right">{{ $money($invoice->subtotal_minor) }}</td></tr>
        @if((int) $invoice->discount_minor > 0)
            <tr><td class="muted">Discount</td><td class="right">-{{ $money($invoice->discount_minor) }}</td></tr>
        @endif
        @forelse($taxRows as $tax)
            @php
                $taxName = $tax['name'] ?? ($tax['label'] ?? 'Tax');
                $rate = isset($tax['rate_bps']) && (int) $tax['rate_bps'] > 0 ? ' (' . rtrim(rtrim(number_format($tax['rate_bps'] / 100, 2), '0'), '.') . '%)' : '';
            @endphp
            <tr><td class="muted">{{ $taxName }}{{ $rate }}</td><td class="right">{{ $money($tax['amount_minor'] ?? 0) }}</td></tr>
        @empty
            @if((int) $invoice->tax_total_minor > 0)
                <tr><td class="muted">Tax</td><td class="right">{{ $money($invoice->tax_total_minor) }}</td></tr>
            @endif
        @endforelse
        <tr class="grand"><td>Total</td><td class="right">{{ $money($invoice->grand_total_minor) }}</td></tr>
    </table>

    <div style="clear: both;"></div>

    @if($invoice->notes_md)
        <div class="notes">
            <p class="section-label">Notes</p>
            <div>{!! nl2br(e($invoice->notes_md)) !!}</div>
        </div>
    @endif

    <div class="foot">
        Invoice {{ $invoice->number }} · {{ $brand['name'] }}@if(!empty($brand['email'])) · {{ $brand['email'] }}@endif<br>
        This is a computer-generated invoice.
    </div>
</body>
</html>
