<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:640px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                @include('common.partials.brand-logo-email')
            </div>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 8px 0;">
                Hi {{ $userName }}, here's your weekly backlink digest
            </h1>
            <p style="font-size:14px; color:#64748b; line-height:1.6; margin:0 0 24px 0;">
                The radar found {{ $totalBacklinks }} new mention{{ $totalBacklinks === 1 ? '' : 's' }}
                across {{ $propertyCount }} of your propert{{ $propertyCount === 1 ? 'y' : 'ies' }} in the last 7 days.
            </p>

            @foreach($properties as $p)
                <div style="border-top:1px solid #e2e8f0; padding:20px 0;">
                    <div style="font-size:13px; text-transform:uppercase; letter-spacing:0.4px; color:#64748b; margin-bottom:4px;">
                        {{ $p['property_label'] }}
                    </div>
                    <div style="font-size:15px; font-weight:600; color:#1e293b; margin-bottom:14px; word-break:break-all;">
                        {{ $p['property_value'] ?: $p['matched_url'] }}
                    </div>

                    @foreach($p['mentions'] as $m)
                        <div style="margin:0 0 14px 0; padding:12px 14px; background:#f1f5f9; border-radius:8px;">
                            <div style="font-size:14px; font-weight:600; color:#1e293b; margin:0 0 4px 0; word-break:break-word;">
                                <a href="{{ $m['page_url'] }}" style="color:#2563eb; text-decoration:none;">
                                    {{ $m['page_title'] ?: $m['page_host'] ?: $m['page_url'] }}
                                </a>
                            </div>
                            <div style="font-size:12px; color:#64748b; margin:0 0 6px 0; word-break:break-all;">
                                {{ $m['page_url'] }}
                            </div>
                            @if(!empty($m['anchor_text']))
                                <div style="font-size:13px; color:#334155; margin:0;">
                                    Anchor text: <em>&ldquo;{{ $m['anchor_text'] }}&rdquo;</em>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach

            <p style="font-size:12px; color:#94a3b8; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:16px;">
                You're receiving this weekly digest because the 1INME backlink radar found new mentions of your properties.
                Don't want these emails? <a href="{{ $unsubscribeUrl }}" style="color:#2563eb;">Unsubscribe in one click</a>
                or change your preferences in your notification settings.
            </p>
        </div>
    </div>
</body>
</html>
