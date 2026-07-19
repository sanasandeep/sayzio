<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 16px rgba(15,23,42,.06);">
                <tr><td style="padding:24px 28px 8px;">
                    <div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#16a34a;font-weight:700;">Shipped</div>
                    <h1 style="margin:8px 0 4px;font-size:22px;line-height:1.3;">{{ $title }}</h1>
                    <div style="color:#6b7280;font-size:14px;">You upvoted this idea, and it just went live on {{ $creator }}'s roadmap.</div>
                </td></tr>
                @if (!empty($desc))
                <tr><td style="padding:8px 28px 0;color:#374151;font-size:15px;line-height:1.55;">
                    {!! nl2br(e(\Illuminate\Support\Str::limit($desc, 600))) !!}
                </td></tr>
                @endif
                <tr><td style="padding:24px 28px;">
                    <a href="{{ $publicUrl }}" style="display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:600;">See what's new</a>
                </td></tr>
                <tr><td style="padding:0 28px 24px;color:#9ca3af;font-size:12px;">
                    Sent because you upvoted or submitted this idea on a public roadmap powered by {{ $appName }}.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
