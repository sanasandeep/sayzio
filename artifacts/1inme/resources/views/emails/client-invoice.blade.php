<!doctype html>
<html><body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background:#f5f5fa; padding: 24px;">
<table cellpadding="0" cellspacing="0" width="100%" style="max-width: 560px; margin: 0 auto; background: white; border-radius: 12px;">
    <tr><td style="padding: 24px;">
        <h2 style="margin: 0 0 8px;">Invoice {{ $invoice->number }}</h2>
        <p style="color: #64748b; margin: 0;">From {{ data_get($invoice->merchant_snapshot, 'name', 'Your service provider') }}</p>
        <p style="margin: 18px 0 6px; font-size: 14px;">Amount due: <strong>{{ strtoupper($invoice->currency) }} {{ number_format($invoice->grand_total_minor / 100, 2) }}</strong></p>
        @if($invoice->due_date)
            <p style="margin: 0; color: #64748b; font-size: 13px;">Due by {{ $invoice->due_date->format('Y-m-d') }}</p>
        @endif
        <p style="margin: 24px 0;">
            <a href="{{ $payUrl }}" style="background: #6366f1; color: white; padding: 12px 22px; font-weight: 700; border-radius: 10px; text-decoration: none;">View &amp; pay invoice</a>
        </p>
        @if($invoice->notes_md)
            <hr style="border: 0; border-top: 1px solid #eef0f5;">
            <p style="color: #475569; white-space: pre-line; font-size: 14px;">{{ $invoice->notes_md }}</p>
        @endif
    </td></tr>
</table>
</body></html>
