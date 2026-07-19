<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                <span style="display:inline-block; font-size:22px; font-weight:700; color:#2563eb; letter-spacing:0.5px;">{{ $appName }}</span>
            </div>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                You're on the list. Welcome!
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                Thanks for subscribing to the {{ $appName }} newsletter. You'll get short, actionable notes on what's working for creators: no fluff, and never more than once a month.
            </p>

            <p style="margin:0 0 24px 0;">
                <a href="{{ $siteUrl }}"
                   style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Visit {{ $appName }}
                </a>
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this because someone (hopefully you) subscribed to the {{ $appName }} newsletter. If that wasn't you, you can
                <a href="{{ $unsubscribeUrl }}" style="color:#64748b; text-decoration:underline;">unsubscribe in one click</a>.
            </p>
        </div>
    </div>
</body>
</html>
