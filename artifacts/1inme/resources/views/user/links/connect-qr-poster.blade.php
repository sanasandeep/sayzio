<!DOCTYPE html>
{{-- Print-ready Connect QR poster (Task #6693). Standalone page (no app
     layout) sized for A4/Letter; opens the print dialog automatically so
     "Print poster" is one tap → print or save-as-PDF. SVG QR only — no
     imagick dependency. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Connect QR poster — {{ $link->title ?: $link->alias }}</title>
    <meta name="robots" content="noindex">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4 portrait; margin: 0; }
        html, body { height: 100%; }
        body {
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        .poster {
            /* A4 proportions; also fits Letter with the same margins. */
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 22mm 18mm;
        }
        @media print {
            body { background: #ffffff; }
            .no-print { display: none !important; }
            .poster { box-shadow: none; margin: 0; }
        }
        @media screen {
            .poster { box-shadow: 0 10px 40px rgba(0,0,0,.15); margin: 24px auto; }
        }
        .kicker {
            font-size: 13pt;
            letter-spacing: .35em;
            text-transform: uppercase;
            color: #2563eb;
            font-weight: 700;
            margin-bottom: 8mm;
        }
        h1 {
            font-size: 34pt;
            line-height: 1.15;
            font-weight: 800;
            margin-bottom: 6mm;
            overflow-wrap: anywhere;
        }
        .meta {
            font-size: 15pt;
            color: #374151;
            margin-bottom: 3mm;
        }
        .meta strong { color: #111827; }
        .qr-frame {
            margin: 10mm auto;
            padding: 8mm;
            border: 1.2mm solid #111827;
            border-radius: 8mm;
            background: #ffffff;
            display: inline-block;
        }
        .qr-frame svg { display: block; width: 120mm; height: 120mm; }
        .instruction {
            font-size: 20pt;
            font-weight: 800;
            margin-bottom: 3mm;
        }
        .sub {
            font-size: 12pt;
            color: #4b5563;
            max-width: 150mm;
            line-height: 1.5;
        }
        .url {
            margin-top: 8mm;
            font-family: ui-monospace, 'Courier New', monospace;
            font-size: 11pt;
            color: #2563eb;
            overflow-wrap: anywhere;
        }
        .print-bar {
            position: fixed;
            top: 12px;
            right: 12px;
        }
        .print-bar button {
            font: inherit;
            font-weight: 700;
            padding: 10px 18px;
            border: 0;
            border-radius: 10px;
            background: #2563eb;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="print-bar no-print">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="poster">
        <div class="kicker">You're invited</div>
        <h1>{{ $ics?->event_name ?: ($link->title ?: $link->alias) }}</h1>

        @if ($ics?->start_date)
            @php
                // Render in the EVENT's timezone (matching event-page.blade.php),
                // never the server default — the printed clock must match the
                // venue clock the label claims.
                try {
                    $posterStart = $ics->start_date->copy()->setTimezone(new \DateTimeZone($ics->timezone ?: 'UTC'));
                    $posterTzLabel = $ics->timezone;
                } catch (\Throwable $e) {
                    $posterStart = $ics->start_date;
                    $posterTzLabel = null;
                }
            @endphp
            <div class="meta">
                <strong>
                    @if ($ics->all_day)
                        {{ $posterStart->format('l, F j, Y') }}
                    @else
                        {{ $posterStart->format('l, F j, Y \a\t g:i A') }}
                        @if ($posterTzLabel) ({{ $posterTzLabel }}) @endif
                    @endif
                </strong>
            </div>
        @endif
        @if ($ics?->location)
            <div class="meta">{{ $ics->location }}</div>
        @endif

        <div class="qr-frame">{!! $qrSvg !!}</div>

        <div class="instruction">Scan to RSVP &amp; connect</div>
        <p class="sub">
            Point your phone's camera at the code. One quick verification code
            signs you in, saves your "Going" RSVP and connects you with the host.
        </p>

        <div class="url">{{ $connectUrl }}</div>
    </div>

    <script>
        // One-tap print: open the dialog once the page (and the inline SVG)
        // has rendered. Guarded so save-as-PDF flows can reopen it manually.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
</body>
</html>
