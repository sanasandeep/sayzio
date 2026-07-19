<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject ?? 'A feature you were waiting for is now available' }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                @include('common.partials.brand-logo-email')
            </div>

            <p style="display:inline-block; background:#eef2ff; color:#4338ca; font-size:12px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; padding:6px 12px; border-radius:999px; margin:0 0 16px 0;">
                Now available
            </p>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                Hi {{ $userName ?? 'there' }}, {{ $featureLabel }} is ready
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                You asked us to let you know when <strong>{{ $featureLabel }}</strong> went live, and it just did. You can start using it right now.
            </p>

            @if(!empty($blurb))
                <p style="font-size:14px; color:#475569; line-height:1.6; margin:0 0 16px 0;">
                    {{ $blurb }}
                </p>
            @endif

            @if(!empty($capabilities) && is_array($capabilities))
                <ul style="margin:0 0 20px 0; padding:0 0 0 20px; color:#334155; font-size:14px; line-height:1.7;">
                    @foreach($capabilities as $capability)
                        <li>{{ $capability }}</li>
                    @endforeach
                </ul>
            @endif

            <p style="margin:0 0 24px 0;">
                <a href="{{ $featureUrl }}"
                   style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Open {{ $featureLabel }}
                </a>
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this once because you asked to be notified when {{ $featureLabel }} became available on Sayzio.
            </p>
        </div>
    </div>
</body>
</html>
