<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Credit Note {{ $credit_note->number }}</title>
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#222; }
  h1 { font-size: 18px; margin: 0 0 4px; }
  .muted { color:#666; }
  table { width: 100%; border-collapse: collapse; margin-top: 18px; }
  th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
  .right { text-align: right; }
  .box { padding: 10px 12px; border:1px solid #ddd; }
</style></head>
<body>
  <table><tr>
    <td>
      <h1>Credit Note</h1>
      <div class="muted">No. <strong>{{ $credit_note->number }}</strong></div>
      <div class="muted">Issued {{ \Carbon\Carbon::parse($credit_note->issued_at)->toFormattedDateString() }}</div>
      <div class="muted">Against invoice <strong>{{ $invoice->number }}</strong></div>
    </td>
    <td class="right">
      <strong>{{ $merchant['name'] ?? '' }}</strong><br>
      {!! nl2br(e($merchant['address'] ?? '')) !!}<br>
      {{ $merchant['tax_id'] ?? '' }}
    </td>
  </tr></table>

  <table style="margin-top:14px">
    <tr>
      <td class="box" width="50%">
        <strong>Billed to</strong><br>
        {{ $address['buyer_name'] ?? '' }}<br>
        {{ $address['business_name'] ?? '' }}<br>
        {{ $address['line1'] ?? '' }} {{ $address['line2'] ?? '' }}<br>
        {{ $address['city'] ?? '' }} {{ $address['region'] ?? '' }} {{ $address['postal_code'] ?? '' }}<br>
        {{ $address['country'] ?? '' }}
      </td>
      <td class="box" width="50%">
        <strong>Reason:</strong><br>
        {{ $credit_note->snapshot['reason'] ?? 'Refund' }}
      </td>
    </tr>
  </table>

  <table>
    <thead><tr><th>Description</th><th class="right">Amount</th></tr></thead>
    <tbody>
      <tr>
        <td>Refund against invoice {{ $invoice->number }}</td>
        <td class="right">{{ number_format($credit_note->amount_minor / 100, 2) }} {{ $credit_note->currency }}</td>
      </tr>
      <tr>
        <td class="right"><strong>Total refunded</strong></td>
        <td class="right"><strong>{{ number_format($credit_note->amount_minor / 100, 2) }} {{ $credit_note->currency }}</strong></td>
      </tr>
    </tbody>
  </table>

  <p class="muted" style="margin-top:18px">This credit note is a record of the refund issued and does not require payment.</p>
</body></html>
