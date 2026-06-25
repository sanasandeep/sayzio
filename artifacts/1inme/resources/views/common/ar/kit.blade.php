<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>AR Kit · {{ $cfg['display_name'] }} · 1INME</title>
<style>
    @page { size: A4; margin: 14mm; }
    * { box-sizing: border-box; }
    body {
        font-family: "DejaVu Sans", sans-serif;
        color: #0f172a;
        margin: 0;
        background: #fff;
    }
    .head {
        border-bottom: 2px solid {{ $cfg['accent_color'] }};
        padding-bottom: 8px; margin-bottom: 18px;
    }
    .head .brand { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: #64748b; }
    .head h1 { font-size: 22px; margin: 4px 0 0; }
    .head .url { font-size: 12px; color: #475569; margin-top: 4px; }
    .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }
    .card {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 14px;
        text-align: center;
        page-break-inside: avoid;
    }
    .card .label { font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: #64748b; margin-bottom: 6px; }
    .card .size  { font-size: 10px; color: #94a3b8; margin-top: 6px; }
    .card .qrwrap { display: inline-block; }
    .card svg { display: block; margin: 0 auto; }
    .nfc {
        margin-top: 22px; padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px;
        background: #f8fafc;
        page-break-inside: avoid;
    }
    .nfc h3 { margin: 0 0 6px; font-size: 14px; }
    .nfc code {
        display: block; padding: 8px; background: #fff; border: 1px solid #e2e8f0;
        border-radius: 4px; font-size: 11px; word-break: break-all; color: #0f172a;
    }
    .instructions { margin-top: 18px; font-size: 11px; color: #475569; line-height: 1.55; }
    .instructions li { margin-bottom: 4px; }
    @if(!$isPdf)
    .actions {
        position: sticky; top: 0; background: #fff; padding: 10px 0;
        border-bottom: 1px solid #e2e8f0; margin-bottom: 16px;
    }
    .actions a { font-size: 12px; padding: 8px 14px; border-radius: 8px;
        background: {{ $cfg['accent_color'] }}; color: #fff;
        text-decoration: none; display: inline-block; margin-right: 8px; }
    .actions a.alt { background: #f1f5f9; color: #0f172a; }
    @endif
</style>
</head>
<body>
@if(!$isPdf)
<div class="actions">
    <a href="{{ route('ar.card.kit.pdf', $link->alias) }}">⬇ Download PDF</a>
    <a href="javascript:window.print()" class="alt">🖨 Print</a>
    <a href="{{ $arUrl }}" class="alt">↗ Preview AR card</a>
</div>
@endif

<div class="head">
    <div class="brand">1INME · AR Card kit</div>
    <h1>{{ $cfg['display_name'] }}</h1>
    <div class="url">{{ $arUrl }}</div>
</div>

<div class="grid">
    @foreach($qrCodes as $key => $qr)
    <div class="card">
        <div class="label">{{ $qr['label'] }}</div>
        <div class="qrwrap">{!! $qr['svg'] !!}</div>
        <div class="size">Print at ~{{ $qr['size_mm'] }} mm wide</div>
    </div>
    @endforeach
</div>

<div class="nfc">
    <h3>NFC tag URL</h3>
    <p style="font-size:11px;color:#475569;margin:0 0 6px;">
        Write this URL to any NTAG-213/215/216 tag with an NFC writer app.
        Tapping the tag launches the AR card on supported phones.
    </p>
    <code>{{ $nfcUrl }}</code>
</div>

<div class="instructions">
    <strong style="font-size:12px;color:#0f172a;">Tips</strong>
    <ul>
        <li>Print QR codes at the recommended size or larger for reliable scanning.</li>
        <li>Test each code with a real phone before mass-printing.</li>
        <li>Each medium carries a unique <em>utm_medium</em> tag — your analytics will split scans by surface.</li>
        <li>Devices that can't run AR will be redirected to the standard Link in Bio with a friendly notice.</li>
    </ul>
</div>
</body>
</html>
