<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><title>Pay invoice {{ $invoice->number }}</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; background: #f5f5fa; color: #1f2937; }
.wrap { max-width: 560px; margin: 40px auto; background: white; border-radius: 14px; padding: 28px; box-shadow: 0 8px 32px rgba(0,0,0,.06); }
.title { font-size: 22px; font-weight: 700; }
.muted { color: #64748b; font-size: 13px; }
.row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eef0f5; font-size: 14px; }
.btn { display: inline-block; margin-top: 20px; background: #6366f1; color: white; padding: 12px 22px; font-weight: 700; border-radius: 10px; text-decoration: none; border: 0; cursor: pointer; }
.paid { color: #059669; font-weight: 700; }
</style></head><body>
<div class="wrap">
    <div class="title">Invoice {{ $invoice->number }}</div>
    <div class="muted">{{ data_get($invoice->merchant_snapshot, 'name', 'Your service provider') }}</div>

    <div style="margin-top: 16px">
        @foreach((array) $invoice->line_items as $li)
            <div class="row"><div>{{ $li['label'] }} × {{ $li['quantity'] ?? 1 }}</div><div>{{ number_format(((int)($li['amount_minor'] ?? 0)) * (int)($li['quantity'] ?? 1) / 100, 2) }}</div></div>
        @endforeach
        @if((int)$invoice->discount_minor > 0)
            <div class="row"><div>Discount</div><div>−{{ number_format($invoice->discount_minor / 100, 2) }}</div></div>
        @endif
        @if((int)$invoice->tax_total_minor > 0)
            <div class="row"><div>Tax</div><div>{{ number_format($invoice->tax_total_minor / 100, 2) }}</div></div>
        @endif
        <div class="row" style="border: 0; font-weight: 700; font-size: 16px;">
            <div>Total due</div>
            <div>{{ strtoupper($invoice->currency) }} {{ number_format($invoice->grand_total_minor / 100, 2) }}</div>
        </div>
    </div>

    @if($paid)
        <p class="paid" style="margin-top: 20px;">✓ Paid on {{ optional($invoice->paid_at)->format('Y-m-d') }}, thank you!</p>
    @else
        {{-- url()->full() preserves the ?expires=…&signature=… query
             string so hasValidSignature() still passes on the POST. --}}
        <form method="POST" action="{{ url()->full() }}">
            @csrf
            <button type="submit" class="btn">Pay with Stripe</button>
        </form>
    @endif

    @if($invoice->notes_md)
        <p class="muted" style="margin-top: 24px; white-space: pre-line;">{{ $invoice->notes_md }}</p>
    @endif
</div>
</body></html>
