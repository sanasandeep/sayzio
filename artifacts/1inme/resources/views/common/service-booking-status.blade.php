@php
    /** @var \App\Modules\User\Models\ServiceBookingRequest $booking */
    /** @var \App\Modules\User\Models\Link $link */
    /** @var \App\Modules\User\Models\ServiceBooking $config */
    $accent = optional($config)->accent_color ?: '#3d6bff';
    $currency = $booking->currency ?: 'USD';
    $title = optional($link)->title ?: optional($link)->alias ?: 'Booking';
    $tz = optional($config)->effectiveTimezone() ?: config('app.timezone', 'UTC');
    $fmt = fn ($n) => $currency . ' ' . number_format((float) $n, 2);
    $when = $booking->slot_start ? \Carbon\Carbon::parse($booking->slot_start)->setTimezone($tz)->format('D, M j · g:i A') : null;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Booking status · {{ $title }}</title>
    <style>
        :root { color-scheme: light dark; --accent: {{ $accent }}; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; min-height:100%; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#f6f6f9; color:#111; }
        @media (prefers-color-scheme: dark) { html, body { background:#0b0b10; color:#f5f5f7; } }
        .wrap { max-width:520px; margin:0 auto; padding:32px 18px 80px; }
        h1 { font-size:22px; font-weight:800; margin:0 0 4px; letter-spacing:-.02em; }
        .sub { opacity:.6; font-size:14px; margin:0 0 20px; }
        .card { background:#fff; border-radius:16px; padding:20px 18px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        @media (prefers-color-scheme: dark) { .card { background:#15151c; box-shadow:none; border:1px solid rgba(255,255,255,.08); } }
        .status-pill { display:inline-block; padding:5px 13px; border-radius:999px; font-size:13px; font-weight:700; background:var(--accent); color:#fff; }
        .when { font-size:16px; font-weight:700; margin:14px 0 4px; }
        .line { display:flex; justify-content:space-between; gap:10px; padding:7px 0; font-size:14px; border-top:1px solid rgba(0,0,0,.07); }
        @media (prefers-color-scheme: dark) { .line { border-color:rgba(255,255,255,.08); } }
        .bill-row { display:flex; justify-content:space-between; font-size:13.5px; margin-top:8px; opacity:.85; }
        .total { display:flex; justify-content:space-between; font-weight:800; font-size:16px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,.1); }
        .note { font-size:12.5px; opacity:.6; margin-top:16px; }
        .sech { font-size:12px; font-weight:700; opacity:.6; text-transform:uppercase; letter-spacing:.04em; margin:18px 0 4px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $title }}</h1>
    <p class="sub">Your booking request</p>
    <div class="card">
        <span class="status-pill" id="bkStatus">{{ $booking->status_label }}</span>
        @if($when)<div class="when">{{ $when }}</div>@endif
        <p class="sub" style="margin:0">{{ $booking->customer_name }}</p>

        <div class="sech">Services</div>
        @foreach($booking->items as $item)
            <div class="line"><span>{{ $item->quantity }}× {{ $item->name }}</span><span>{{ $fmt($item->line_total) }}</span></div>
        @endforeach

        <div class="bill-row"><span>Subtotal</span><span>{{ $fmt($booking->subtotal) }}</span></div>
        @if((float) $booking->tax_amount > 0)
            <div class="bill-row"><span>Tax ({{ (float) $booking->tax_rate }}%){{ $booking->tax_inclusive ? ' incl.' : '' }}</span><span>{{ $fmt($booking->tax_amount) }}</span></div>
        @endif
        <div class="total"><span>Estimated total</span><span>{{ $fmt($booking->total) }}</span></div>

        <p class="note">This is an estimated price, not a final bill. No online payment is taken — you'll settle with the provider directly. This page updates automatically.</p>
    </div>
</div>
<script>
(function () {
    const POLL_URL = @json(route('sb.public.booking.status', ['token' => $booking->public_token]));
    setInterval(async () => {
        try {
            const r = await fetch(POLL_URL);
            if (!r.ok) return;
            const j = await r.json();
            const el = document.getElementById('bkStatus');
            if (el && j.data && j.data.booking) el.textContent = j.data.booking.status_label;
        } catch(e){}
    }, 8000);
})();
</script>
</body>
</html>
