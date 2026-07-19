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
                @include('common.partials.brand-logo-email')
            </div>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                Hi {{ $userName }}, your {{ $platformLabel }} link stopped working
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                We tried to refresh your <strong>{{ $platformLabel }}</strong> connection
                @if($handle) (<span style="color:#475569;">&#64;{{ $handle }}</span>) @endif
                and {{ $platformLabel }} rejected the request. Until you reconnect, the Follow button on your Sayzio page won't update follower counts and may stop working for visitors.
            </p>

            @if(!empty($reason))
                <p style="font-size:12px; color:#64748b; line-height:1.5; margin:0 0 20px 0; background:#f1f5f9; padding:10px 12px; border-radius:8px; word-break:break-word;">
                    <strong style="color:#475569;">Details:</strong> {{ \Illuminate\Support\Str::limit($reason, 240) }}
                </p>
            @endif

            <p style="margin:0 0 24px 0;">
                <a href="{{ $reconnectUrl }}"
                   style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Reconnect {{ $platformLabel }}
                </a>
            </p>

            <p style="font-size:13px; color:#64748b; line-height:1.6; margin:0;">
                Reconnecting takes about 10 seconds; you'll be redirected to {{ $platformLabel }} to re-grant access, then back to your Sayzio dashboard.
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this because a social account you connected to Sayzio stopped responding. We send at most one of these per connection per week.
                @if(!empty($unsubscribeUrl))
                    <a href="{{ $unsubscribeUrl }}" style="color:#64748b; text-decoration:underline;">Unsubscribe from broken-connection emails</a>
                    in one click, or manage all notification preferences in your profile settings.
                @else
                    Manage your notification preferences in your profile settings.
                @endif
            </p>
        </div>
    </div>
</body>
</html>
