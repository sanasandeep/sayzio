<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Setup required &mdash; Sayzio</title>
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
            max-width: 600px; width: 100%;
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
        h1 { font-size: 28px; margin: 20px 0 10px; font-weight: 700; }
        p  { font-size: 15px; line-height: 1.6; color: rgba(231,231,240,0.78); margin: 8px 0; }
        .cmd {
            margin: 22px auto 6px; max-width: 420px;
            display: block; text-align: left;
            padding: 14px 18px;
            background: rgba(10,10,20,0.7);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 12px;
            font-family: "SF Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 14px; color: #93c5fd;
        }
        .cmd .prompt { color: rgba(231,231,240,0.4); user-select: none; }
        .hint { margin-top: 14px; font-size: 13px; color: rgba(231,231,240,0.55); }
        .meta { margin-top: 24px; font-size: 11px; color: rgba(231,231,240,0.4); letter-spacing: 0.06em; text-transform: uppercase; }
        .dot { width: 8px; height: 8px; border-radius: 999px; background: #c4b5fd; box-shadow: 0 0 8px #c4b5fd; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge"><span class="dot"></span> Setup required</span>
        <h1>Your database needs migrating.</h1>
        <p>
            Sayzio is up and running, but the database is missing core tables, so
            pages can&rsquo;t load yet. This usually means migrations haven&rsquo;t
            been run in this environment.
        </p>
        <p>Run the database migrations to finish setup:</p>
        <code class="cmd"><span class="prompt">$ </span>php artisan migrate</code>
        <p class="hint">Once migrations complete, refresh this page &mdash; everything will load normally.</p>
        <div class="meta">Developer setup notice</div>
    </div>
</body>
</html>
