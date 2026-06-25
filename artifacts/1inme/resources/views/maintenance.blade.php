<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    @php $style = ($style ?? 'standard') === 'upgrade' ? 'upgrade' : 'standard'; @endphp
    <title>@if($style === 'upgrade')Sayzio 2.0 is coming@else We&rsquo;ll be right back @endif &mdash; Sayzio</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Inter, sans-serif;
            background: radial-gradient(1200px 600px at 20% 10%, rgba(124,58,237,0.18), transparent 60%),
                        radial-gradient(900px 500px at 90% 100%, rgba(59,130,246,0.18), transparent 60%),
                        #0b0b14;
            color: #e7e7f0;
            display: flex; align-items: center; justify-content: center;
            padding: 32px 20px;
        }
        .card {
            max-width: 560px; width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 40px 32px;
            backdrop-filter: blur(12px);
            text-align: center;
        }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 12px; border-radius: 999px; font-size: 12px;
            background: rgba(245, 158, 11, 0.12);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
        }
        h1 { font-size: 28px; margin: 20px 0 10px; font-weight: 700; }
        p  { font-size: 15px; line-height: 1.6; color: rgba(231,231,240,0.78); margin: 8px 0; }
        .eta {
            margin-top: 18px; padding: 12px 16px;
            background: rgba(124,58,237,0.10);
            border: 1px solid rgba(124,58,237,0.30);
            border-radius: 12px; font-size: 13px; color: #c4b5fd;
        }
        .dot { width: 8px; height: 8px; border-radius: 999px; background: #fbbf24; box-shadow: 0 0 8px #fbbf24; animation: pulse 1.6s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.35; } }
        .meta { margin-top: 24px; font-size: 11px; color: rgba(231,231,240,0.4); letter-spacing: 0.06em; text-transform: uppercase; }

        /* ---- Sayzio 2.0 "upgrade" variant ---- */
        .logo { font-size: 13px; font-weight: 800; letter-spacing: 0.2em; color: #a78bfa; text-transform: uppercase; margin-bottom: 16px; }
        .badge.upgrade {
            background: rgba(124,58,237,0.14);
            border: 1px solid rgba(124,58,237,0.4);
            color: #c4b5fd;
        }
        .badge.upgrade .dot { background: #a78bfa; box-shadow: 0 0 8px #a78bfa; }
        .hero {
            font-size: 34px; line-height: 1.2; margin: 18px 0 12px; font-weight: 800;
            background: linear-gradient(90deg, #c4b5fd, #93c5fd 55%, #f0abfc);
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        .feature {
            margin-top: 22px; padding: 18px 18px 16px;
            background: rgba(124,58,237,0.08);
            border: 1px solid rgba(124,58,237,0.28);
            border-radius: 16px; text-align: left;
        }
        .feature .kicker { font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase; color: #a78bfa; font-weight: 700; }
        .feature .ft { font-size: 16px; font-weight: 700; margin: 6px 0 4px; color: #f3f0ff; }
        .feature .fb { font-size: 13.5px; line-height: 1.55; color: rgba(231,231,240,0.74); margin: 0; }
        .note { margin-top: 18px; font-size: 13px; color: rgba(231,231,240,0.7); }
    </style>
</head>
<body>
    @if($style === 'upgrade')
        {{--
            "Sayzio 2.0" upgrade announcement. The headline + teaser copy below are
            plain text and safe to edit. This task only adds the announcement; the
            actual AI "digital aging" feature is built separately.
        --}}
        <div class="card">
            <div class="logo">Sayzio</div>
            <span class="badge upgrade"><span class="dot"></span> Sayzio 2.0 &middot; Coming soon</span>
            <h1 class="hero">Something new is taking shape.</h1>
            <p>
                We&rsquo;re upgrading Sayzio to <strong>2.0</strong>. We&rsquo;ve taken the
                site offline for a short while to put the finishing touches on it &mdash;
                thanks for your patience.
            </p>

            <div class="feature">
                <div class="kicker">Sneak peek</div>
                <div class="ft">Introducing AI &ldquo;Digital Aging&rdquo;</div>
                <p class="fb">
                    A brand-new AI experience that shows how your links &mdash; and you &mdash;
                    evolve over time. More to share when we&rsquo;re back.
                </p>
            </div>

            @if(!empty($message))
                <p class="note">{{ $message }}</p>
            @endif
            @if(!empty($eta))
                <div class="eta"><strong>Estimated back online:</strong> {{ $eta }}</div>
            @endif
            <div class="meta">{{ $label ?? 'Service' }}</div>
        </div>
    @else
        <div class="card">
            <span class="badge"><span class="dot"></span> Scheduled maintenance</span>
            <h1>We&rsquo;ll be right back.</h1>
            <p>
                @if(!empty($message))
                    {{ $message }}
                @else
                    This part of Sayzio is temporarily offline while we ship an improvement. Please check back in a few minutes.
                @endif
            </p>
            @if(!empty($eta))
                <div class="eta"><strong>Estimated back online:</strong> {{ $eta }}</div>
            @endif
            <div class="meta">{{ $label ?? 'Service' }}</div>
        </div>
    @endif
</body>
</html>
