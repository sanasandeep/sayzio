{{--
    Standalone 503 for short-link resolution when the database is
    unreachable / un-migrated. Deliberately self-contained: no layout,
    no partials, ZERO database reads — this page must render during the
    exact outage that triggered it (see RedirectController::handle).
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Temporarily unavailable</title>
    <meta name="robots" content="noindex">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
               background: #f8fafc; color: #1a1a1a; margin: 0; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 480px; background: #fff; border: 1px solid #e2e8f0;
                border-radius: 12px; padding: 32px; text-align: center;
                box-shadow: 0 1px 3px rgba(0,0,0,.04); }
        h1 { font-size: 22px; margin: 0 0 12px; }
        p { color: #475569; line-height: 1.5; margin: 0 0 8px; }
        .icon { font-size: 36px; margin-bottom: 12px; }
        .muted { color: #94a3b8; font-size: 13px; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">&#9888;&#65039;</div>
        <h1>This link is temporarily unavailable</h1>
        <p>We're having a brief technical hiccup on our end. The link itself is fine &mdash; please try again in a minute or two.</p>
        <p class="muted">Error 503 &middot; Service temporarily unavailable</p>
    </div>
</body>
</html>
