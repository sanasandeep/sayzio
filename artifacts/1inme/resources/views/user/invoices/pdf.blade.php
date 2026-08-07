@php
    use App\Services\PricingResolver;
    $cur = $invoice->currency;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tax Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color:#1a1a1a; font-size:11px; line-height:1.45; }
        h1 { font-size:18px; margin:0 0 4px; color:#3d6bff; letter-spacing:0.5px; }
        h2 { font-size:13px; margin:16px 0 6px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:6px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        th { text-align:left; background:#f3f4f6; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; }
        .right { text-align:right; }
        .totals td { border:0; padding:3px 8px; }
        .totals .grand { font-weight:bold; border-top:2px solid #1a1a1a; font-size:13px; }
        .meta { width:100%; margin-bottom:8px; }
        .meta td { border:0; padding:2px 0; }
        .muted { color:#6b7280; font-size:10px; }
        .badge { background:#fde68a; color:#92400e; padding:2px 6px; font-size:10px; border-radius:3px; }
    </style>
</head>
<body>
    <table class="meta">
        <tr>
            <td style="width:60%">
                <h1>{{ $merchant['name'] ?? 'Merchant' }}</h1>
                <div class="muted">{{ $merchant['address'] ?? '' }}</div>
                @if(!empty($merchant['gstin']))
                    <div class="muted">GSTIN: {{ $merchant['gstin'] }}</div>
                @endif
                @if(!empty($merchant['vatin']))
                    <div class="muted">VAT: {{ $merchant['vatin'] }}</div>
                @endif
            </td>
            <td class="right" style="width:40%">
                <h2 style="margin:0;color:#3d6bff">TAX INVOICE</h2>
                <div><strong>{{ $invoice->number }}</strong></div>
                <div class="muted">Issued {{ optional($invoice->issued_at)->format('d M Y') }}</div>
                <div class="muted">FY {{ $invoice->financial_year }}</div>
                @if($invoice->place_of_supply)
                    <div class="muted">Place of supply: {{ $invoice->place_of_supply }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h2>Bill to</h2>
    <div>
        @php
            $buyerName = $address['buyer_name'] ?? optional($invoice->user)->name ?? '';
            $businessName = $address['business_name'] ?? '';
        @endphp
        <strong>{{ $buyerName !== '' ? $buyerName : $businessName }}</strong><br>
        @if($businessName !== '' && $businessName !== $buyerName)
            {{ $businessName }}<br>
        @endif
        {{ $address['line1'] ?? '' }} {{ $address['line2'] ?? '' }}<br>
        {{ $address['city'] ?? '' }} {{ $address['region'] ?? '' }} {{ $address['postal_code'] ?? '' }}<br>
        {{ $address['country'] ?? '' }}
        @if(!empty($address['tax_id']))
            @php
                $taxLabel = ($address['tax_id_kind'] ?? '') === 'OTHER'
                    ? ($address['tax_id_label'] ?? 'Tax ID')
                    : ($address['tax_id_kind'] ?? 'Tax ID');
            @endphp
            <br><span class="muted">{{ $taxLabel }}: {{ $address['tax_id'] }}</span>
        @endif
    </div>

    <h2>Items</h2>
    <table>
        <thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Unit</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @foreach($invoice->line_items as $li)
                <tr>
                    <td>{{ $li['label'] }}</td>
                    <td class="right">{{ $li['quantity'] }}</td>
                    <td class="right">{{ PricingResolver::money((int) $li['amount_minor'], $cur) }}</td>
                    <td class="right">{{ PricingResolver::money((int) $li['line_total_minor'], $cur) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:8px;width:55%;float:right">
        <tr><td>Subtotal</td><td class="right">{{ PricingResolver::money((int) $invoice->subtotal_minor, $cur) }}</td></tr>
        @foreach(($invoice->tax_breakdown ?? []) as $tax)
            <tr>
                <td>{{ $tax['label'] }}</td>
                <td class="right">{{ PricingResolver::money((int) $tax['amount_minor'], $cur) }}</td>
            </tr>
        @endforeach
        <tr class="grand"><td>Total</td><td class="right">{{ PricingResolver::money((int) $invoice->grand_total_minor, $cur) }}</td></tr>
    </table>

    <div style="clear:both"></div>

    @if($invoice->reverse_charge_note)
        <p style="margin-top:24px"><span class="badge">Reverse charge</span> {{ $invoice->reverse_charge_note }}</p>
    @endif

    <p class="muted" style="margin-top:32px">
        Questions? Contact {{ $merchant['support_email'] ?? 'billing@sayzio.app' }}.
        This is a computer-generated tax invoice; no signature required.
    </p>
</body>
</html>
