@php
    $cur   = strtoupper($invoice->currency ?: 'USD');
    $money = fn ($m) => $cur . ' ' . number_format(((int) $m) / 100, 2);
    $lines = is_array($invoice->line_items) ? $invoice->line_items : [];
    $taxRows = is_array($invoice->tax_breakdown) ? $invoice->tax_breakdown : [];
    $refunded = (int) $invoice->refundedTotalMinor();
    $letterhead = $letterhead ?? ['image_data_uri' => null, 'margins' => ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0]];
    $lm = $letterhead['margins'] ?? ['top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0];
    $pageMargin = sprintf('%dmm %dmm %dmm %dmm', 32 + (int) ($lm['top'] ?? 0), 36 + (int) ($lm['right'] ?? 0), 32 + (int) ($lm['bottom'] ?? 0), 36 + (int) ($lm['left'] ?? 0));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receipt->number }}</title>
    <style>
        @page {
            margin: {{ $pageMargin }};
            @if(!empty($letterhead['image_data_uri']))
            background-image: url('{{ $letterhead['image_data_uri'] }}');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            @endif
        }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1f2430; font-size: 11px; line-height: 1.5; }
        .head { width: 100%; margin-bottom: 24px; }
        .head td { vertical-align: top; }
        .logo { max-height: 56px; max-width: 200px; margin-bottom: 6px; }
        .brand-name { font-size: 17px; font-weight: bold; color: #111827; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 10px; }
        .doc-title { font-size: 22px; font-weight: bold; color: #15803d; letter-spacing: 0.5px; margin: 0; text-align: right; }
        .doc-meta { text-align: right; margin-top: 4px; }
        .doc-meta div { margin: 1px 0; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 10px; font-weight: bold; letter-spacing: 0.4px; background: #dcfce7; color: #15803d; }
        .section-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.6px; color: #9ca3af; margin: 0 0 4px; }
        table.meta { width: 100%; margin: 18px 0 20px; border-collapse: collapse; }
        table.meta td { vertical-align: top; width: 25%; padding: 0 12px 10px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.items th { text-align: left; background: #f0fdf4; color: #166534; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px; border-bottom: 2px solid #dcfce7; }
        table.items td { padding: 8px; border-bottom: 1px solid #eef0f4; vertical-align: top; }
        .right { text-align: right; }
        .totals { width: 46%; float: right; margin-top: 10px; border-collapse: collapse; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 2px solid #1f2430; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .totals .refund td { color: #be123c; }
        .foot { clear: both; margin-top: 36px; text-align: center; color: #9ca3af; font-size: 9px; }
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
                @foreach($brand['tax_ids'] as $tid)
                    <div class="muted">{{ $tid }}</div>
                @endforeach
            </td>
            <td style="width: 42%;">
                <p class="doc-title">RECEIPT</p>
                <div class="doc-meta">
                    <div><strong>{{ $receipt->number }}</strong></div>
                    <div class="muted">For invoice {{ $invoice->number }}</div>
                    <div style="margin-top: 6px;"><span class="badge">PAID</span></div>
                </div>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td>
                <p class="section-label">Date</p>
                <div>{{ optional($receipt->paid_at ?: $receipt->created_at)->format('d M Y') }}</div>
            </td>
            <td>
                <p class="section-label">Method</p>
                <div>{{ ucfirst($receipt->method ?: 'manual') }}@if($receipt->gateway) · {{ $receipt->gateway }}@endif</div>
            </td>
            <td>
                <p class="section-label">Billed to</p>
                @if($invoice->recipient_name)
                    <div><strong>{{ $invoice->recipient_name }}</strong></div>
                @endif
                <div>{{ $invoice->recipient_email ?: ($invoice->recipient_name ? '' : '—') }}</div>
            </td>
            <td>
                <p class="section-label">Reference</p>
                <div>{{ $receipt->gateway_ref ?: '—' }}</div>
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
        <tr class="grand"><td>Total paid</td><td class="right">{{ $money($receipt->amount_minor ?: $invoice->grand_total_minor) }}</td></tr>
        @if($refunded > 0)
            <tr class="refund"><td>Refunded</td><td class="right">-{{ $money($refunded) }}</td></tr>
        @endif
    </table>

    <div class="foot">
        Receipt {{ $receipt->number }} · {{ $brand['name'] }}@if(!empty($brand['email'])) · {{ $brand['email'] }}@endif<br>
        Thank you for your payment. This is a computer-generated receipt.
    </div>
</body>
</html>
