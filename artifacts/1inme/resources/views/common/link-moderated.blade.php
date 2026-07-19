<!doctype html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="utf-8">
    <title>Page unavailable</title>
    <meta name="robots" content="noindex">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
               background: #0a0612; color: #f8fafc; margin: 0; min-height: 100vh;
               display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { max-width: 480px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
                border-radius: 16px; padding: 32px; text-align: center; }
        h1 { font-size: 20px; margin: 0 0 12px; }
        p { color: rgba(248,250,252,0.7); line-height: 1.55; font-size: 14px; }
        .icon { font-size: 32px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚫</div>
        <h1>This page is currently unavailable</h1>
        <p>Our team has temporarily removed this Link in Bio while we review reports about its content.</p>
    </div>
</body>
</html>
