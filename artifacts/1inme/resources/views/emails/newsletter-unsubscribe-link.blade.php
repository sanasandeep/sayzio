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
                Unsubscribe from the {{ $appName }} newsletter
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                You (or someone using your email address) requested an unsubscribe link from our subscription center. Click the button below and you'll be removed from the email newsletter, no login required.
            </p>

            <p style="margin:0 0 24px 0;">
                <a href="{{ $unsubscribeUrl }}"
                   style="display:inline-block; background-color:#dc2626; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Unsubscribe in one click
                </a>
            </p>

            <p style="font-size:13px; color:#475569; line-height:1.6; margin:0 0 8px 0;">
                Want to stop our WhatsApp Channel updates instead? Open the channel in WhatsApp and tap <strong>Unfollow</strong>.<br>
                Want to stop WhatsApp DMs? Reply <strong>STOP</strong> to the conversation.
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                Didn't ask for this email? You can safely ignore it; no changes will be made to your subscription unless you click the button above.
                You can also visit <a href="{{ $siteUrl }}" style="color:#64748b; text-decoration:underline;">{{ $appName }}</a> directly.
            </p>
        </div>
    </div>
</body>
</html>
