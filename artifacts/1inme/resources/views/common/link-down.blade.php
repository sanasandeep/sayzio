<!doctype html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="utf-8">
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
        p { color: #475569; line-height: 1.5; }
        .icon { font-size: 36px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⚠️</div>
        <h1>This link is temporarily unavailable</h1>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
