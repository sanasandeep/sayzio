<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New sign-in to your 1INME account</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                <span style="display:inline-block; font-size:22px; font-weight:700; color:#2563eb; letter-spacing:0.5px;">1INME</span>
            </div>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                Hi {{ $user->name }}, we noticed a new sign-in
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                Your 1INME account was just signed into from a {{ $reasonsLabel }} we haven't seen before. If this was you, no further action is needed.
            </p>

            <table style="width:100%; border-collapse:collapse; margin:0 0 20px 0; font-size:13px; color:#334155;">
                <tr>
                    <td style="padding:8px 0; color:#64748b; width:40%;">When</td>
                    <td style="padding:8px 0;">{{ $when }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:#64748b;">Location</td>
                    <td style="padding:8px 0;">{{ $location }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0; color:#64748b;">Device</td>
                    <td style="padding:8px 0;">{{ $event->device_label ?: 'Unknown device' }}</td>
                </tr>
                @if($event->ip)
                <tr>
                    <td style="padding:8px 0; color:#64748b;">IP address</td>
                    <td style="padding:8px 0; font-family:monospace;">{{ $event->ip }}</td>
                </tr>
                @endif
            </table>

            <p style="margin:0 0 8px 0; font-size:14px; color:#1e293b;">
                <strong>Wasn't you?</strong> Tap the button below to immediately sign that session out, log out every other device, and reset your password.
            </p>

            <p style="margin:16px 0 24px 0;">
                <a href="{{ $revokeUrl }}"
                   style="display:inline-block; background-color:#dc2626; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    This wasn't me
                </a>
            </p>

            <p style="font-size:12px; color:#94a3b8; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this because a sign-in was detected from a new device, browser, or country. We send this only when the login looks unusual — there's nothing to manage in preferences.
            </p>
        </div>
    </div>
</body>
</html>
