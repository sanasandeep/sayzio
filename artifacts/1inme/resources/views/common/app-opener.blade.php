<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>Opening {{ $app['name'] }}…</title>
    <style>
        :root { color-scheme: dark; }
        html, body { margin: 0; padding: 0; min-height: 100vh; background: #0b0b14; color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Inter, sans-serif; }
        .wrap { min-height: 100vh; display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 24px; text-align: center; }
        .badge { width: 84px; height: 84px; border-radius: 22px; background: linear-gradient(135deg,#3d6bff,#6e61ff);
            display: flex; align-items: center; justify-content: center; font-size: 38px; margin-bottom: 20px;
            box-shadow: 0 18px 48px -12px rgba(61,107,255,.55); }
        h1 { font-size: 18px; font-weight: 600; margin: 0 0 6px; }
        p  { font-size: 13px; opacity: .6; margin: 0 0 22px; }
        .btn { display: inline-block; padding: 12px 22px; border-radius: 14px; font-size: 14px; font-weight: 600;
            text-decoration: none; background: #3d6bff; color: #fff; border: 0; cursor: pointer; }
        .btn.secondary { background: rgba(255,255,255,0.08); margin-left: 10px; }
        .row { margin-top: 6px; }
        .pulse { animation: pulse 1.4s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .55 } }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="badge">{!! $app['emoji'] ?? '🔗' !!}</div>
        <h1 class="pulse">Opening {{ $app['name'] }}…</h1>
        <p>If the app doesn't open in a moment, use the buttons below.</p>
        <div class="row">
            <a class="btn" id="open-app-btn" href="{{ $appUrl }}">Open {{ $app['name'] }} app</a>
            <a class="btn secondary" href="{{ $webUrl }}">Continue in browser</a>
        </div>
    </div>

    <script>
        (function () {
            var appUrl = {!! json_encode($appUrl) !!};
            var webUrl = {!! json_encode($webUrl) !!};
            var fired = false;
            var startedAt = Date.now();

            // Try to open the app immediately. On Android, intent:// will
            // fall back to S.browser_fallback_url (Play Store) automatically.
            // On iOS, custom schemes silently fail if the app isn't installed,
            // so we set a timer that sends the user to the web URL if we are
            // still on this page after a short delay.
            function tryOpenApp() {
                if (fired) return;
                fired = true;
                window.location.href = appUrl;
            }

            // Bail to web if app didn't take over within ~2.2s and the page
            // was visible the whole time (a backgrounded tab means the OS
            // probably handed off to the app).
            setTimeout(function () {
                if (Date.now() - startedAt < 2000) return;
                if (document.visibilityState === 'visible') {
                    window.location.href = webUrl;
                }
            }, 2200);

            // Defer the redirect so the meta/title paint first — feels
            // smoother than blanking the screen instantly.
            setTimeout(tryOpenApp, 80);
        })();
    </script>
</body>
</html>
