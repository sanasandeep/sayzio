<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unsubscribed — Sayzio</title>
    <style>
        body { margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; background:#f8fafc; color:#0f172a; }
        .wrap { max-width: 520px; margin: 64px auto; padding: 0 16px; }
        .card { background:#ffffff; border-radius:12px; padding:32px; box-shadow: 0 1px 3px rgba(15,23,42,0.08); }
        .brand { font-size:22px; font-weight:700; color:#2563eb; letter-spacing:0.5px; margin-bottom:16px; }
        h1 { font-size:20px; margin:0 0 12px 0; color:#0f172a; }
        p { font-size:14px; line-height:1.6; color:#334155; margin:0 0 12px 0; }
        a.btn { display:inline-block; margin-top:16px; background:#2563eb; color:#ffffff; padding:10px 18px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; }
        .muted { color:#64748b; font-size:12px; margin-top:20px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="brand">Sayzio</div>
            <h1>You're unsubscribed</h1>
            <p>
                {{ $user->name ? $user->name . ', we' : 'We' }}'ve turned off broken-connection emails for your account.
                We won't email you when a connected social account stops refreshing.
            </p>
            <p>
                You'll still see the warning badges and in-app alerts on your Connected Accounts page so nothing slips through silently.
            </p>
            <a class="btn" href="{{ route('user.social-accounts.index') }}">Open Connected Accounts</a>
            <p class="muted">
                Changed your mind? Re-enable these emails any time from the toggle near the health badges on the Connected Accounts page.
            </p>
        </div>
    </div>
</body>
</html>
