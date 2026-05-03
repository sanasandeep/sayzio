<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connecting 1INME extension…</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
               color: white; min-height: 100vh; margin: 0;
               display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: rgba(255,255,255,0.12); backdrop-filter: blur(8px);
                border-radius: 16px; padding: 32px 28px; max-width: 420px; text-align: center; }
        h1 { margin: 0 0 8px; font-size: 22px; }
        p  { margin: 6px 0; opacity: 0.92; line-height: 1.5; }
        .pulse { display: inline-block; width: 14px; height: 14px; border-radius: 50%;
                 background: #34d399; box-shadow: 0 0 0 0 rgba(52,211,153,0.7);
                 animation: pulse 1.4s infinite; margin-right: 8px; vertical-align: middle; }
        @keyframes pulse {
            0%   { box-shadow: 0 0 0 0 rgba(52,211,153,0.7); }
            70%  { box-shadow: 0 0 0 14px rgba(52,211,153,0); }
            100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1><span class="pulse"></span>Connecting…</h1>
        <p>Linking your 1INME account to the browser extension.</p>
        <p>You can close this tab once it disappears automatically.</p>
    </div>

    {{-- Read by the extension's content-handshake.js (matched on this URL). --}}
    <script id="extension-handshake" type="application/json">{!! json_encode($payload, JSON_UNESCAPED_SLASHES) !!}</script>
</body>
</html>
