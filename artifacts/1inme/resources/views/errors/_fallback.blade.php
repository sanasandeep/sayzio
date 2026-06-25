{{--
    Last-resort, fully self-contained error page.

    This page deliberately depends on NOTHING that can fail during an outage:
    no database calls, no @vite/asset manifest, no `public.layouts.site`, no
    SitePage lookups. It is rendered (see errors/_render.blade.php) only in
    production and only when the rich branded error view itself fails to render
    (e.g. the database is unreachable or the Vite manifest is missing). Styling
    mirrors maintenance.blade.php so the brand stays consistent.
--}}
@php
    $sc = (int) ($statusCode ?? 500);
    $map = [
        403 => ['No access', "You don't have permission to view this page."],
        404 => ['Page not found', "The page you're looking for doesn't exist or has moved."],
        405 => ["That isn't a valid page", "This link can't be opened the way you reached it. Head back to where you started."],
        419 => ['Your session expired', 'For your security, your session timed out. Please refresh and try again.'],
        429 => ['Slow down a moment', "You've made a few too many requests. Please wait a little and try again."],
        500 => ['Something went wrong on our end', "Sorry — an unexpected error occurred. Our team has been notified. Please try again in a few minutes."],
        503 => ["We'll be right back", 'The site is temporarily down for maintenance. Please check back shortly.'],
    ];
    [$title, $body] = $map[$sc] ?? ['Something went wrong', 'An unexpected error occurred on our side. Please try again in a moment.'];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} &mdash; Sayzio</title>
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
            background: rgba(124,58,237,0.12);
            border: 1px solid rgba(124,58,237,0.35);
            color: #c4b5fd; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
        }
        .logo { font-size: 13px; font-weight: 800; letter-spacing: 0.2em; color: #a78bfa; text-transform: uppercase; margin-bottom: 18px; }
        h1 { font-size: 28px; margin: 20px 0 10px; font-weight: 700; }
        p  { font-size: 15px; line-height: 1.6; color: rgba(231,231,240,0.78); margin: 8px 0; }
        .cta {
            display: inline-block; margin-top: 24px; padding: 12px 22px;
            background: #7c3aed; color: #fff; text-decoration: none;
            border-radius: 999px; font-size: 14px; font-weight: 700;
        }
        .cta:hover { background: #6d28d9; }
        .meta { margin-top: 24px; font-size: 11px; color: rgba(231,231,240,0.4); letter-spacing: 0.06em; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">Sayzio</div>
        <span class="badge">Error {{ $sc }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
        <a class="cta" href="/">Back to home</a>
        <div class="meta">Sayzio</div>
    </div>
</body>
</html>
