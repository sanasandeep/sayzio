<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject ?? 'The ' . ($appName ?? 'Sayzio') . ' app is here' }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                @include('common.partials.brand-logo-email')
            </div>

            <p style="display:inline-block; background:#eef2ff; color:#4338ca; font-size:12px; font-weight:700; letter-spacing:0.04em; text-transform:uppercase; padding:6px 12px; border-radius:999px; margin:0 0 16px 0;">
                It's here
            </p>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                The {{ $appName ?? 'Sayzio' }} mobile app just launched
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                You asked us to let you know the moment the {{ $appName ?? 'Sayzio' }} app hit the stores — it just did.
                Manage your links, biolinks and QR codes, chat with your audience and watch your stats
                live, all from your pocket.
            </p>

            @php
                $__appPlayUrl = $playUrl ?? '';
                $__appAppUrl  = $appUrl ?? '';
                $__appCta     = $storeUrl ?? ($__appPlayUrl ?: $__appAppUrl);
            @endphp

            @if($__appPlayUrl !== '' || $__appAppUrl !== '')
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
                    <tr>
                        @if($__appPlayUrl !== '')
                            <td style="padding:0 10px 0 0;">
                                <a href="{{ $__appPlayUrl }}"
                                   style="display:inline-block; background-color:#0d0f17; color:#ffffff; padding:12px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                                    Get it on Google Play
                                </a>
                            </td>
                        @endif
                        @if($__appAppUrl !== '')
                            <td style="padding:0;">
                                <a href="{{ $__appAppUrl }}"
                                   style="display:inline-block; background-color:#0d0f17; color:#ffffff; padding:12px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                                    Download on the App Store
                                </a>
                            </td>
                        @endif
                    </tr>
                </table>
            @elseif($__appCta !== '')
                <p style="margin:0 0 24px 0;">
                    <a href="{{ $__appCta }}"
                       style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                        Download the app
                    </a>
                </p>
            @endif

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this once because you asked to be notified when the {{ $appName ?? 'Sayzio' }} mobile app became available.
                @if(!empty($unsubscribeUrl))
                    Prefer not to hear from us? <a href="{{ $unsubscribeUrl }}" style="color:#64748b; text-decoration:underline;">Unsubscribe in one click</a>.
                @endif
            </p>
        </div>
    </div>
</body>
</html>
