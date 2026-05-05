<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 560px; margin: 0 auto; background: white; border-radius: 12px; padding: 36px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 22px; color: #1e293b; margin: 0 0 6px;">{{ config('app.name') }}</h1>
        <h2 style="font-size: 16px; color: #b91c1c; margin: 0 0 18px;">
            @if(count($grants) === 1)
                Platform admin role granted
            @else
                {{ count($grants) }} platform admin roles granted
            @endif
        </h2>

        <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
            <strong>{{ $actorLabel }}</strong> just granted
            @if(count($grants) === 1)
                <strong>{{ $grants[0]['roleLabel'] }}</strong>
            @else
                <strong>{{ count($grants) }} platform-admin level roles</strong>
            @endif
            to <strong>{{ $targetLabel }}</strong>.
            @if(count($grants) === 1)
                This role carries platform-admin powers — please confirm it
                was authorised.
            @else
                These roles all carry platform-admin powers — please confirm
                the grants were authorised.
            @endif
        </p>

        @foreach($grants as $g)
            @php $audit = $g['audit']; @endphp
            <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin: 0 0 14px;">
                <table role="presentation" style="width:100%; font-size: 13px; color: #475569; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 4px 0; width: 110px; color: #64748b;">When</td>
                        <td style="padding: 4px 0;">{{ $audit->created_at?->toDayDateTimeString() }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Role</td>
                        <td style="padding: 4px 0;">
                            {{ $g['roleLabel'] }}
                            <span style="font-family: ui-monospace, Menlo, monospace; color:#94a3b8;">({{ $audit->role_slug }})</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Source</td>
                        <td style="padding: 4px 0;">
                            {{ $audit->source === 'admin'
                                ? 'Back-office admin panel'
                                : 'Self-service user-access page' }}
                        </td>
                    </tr>
                    @if($audit->ip)
                        <tr>
                            <td style="padding: 4px 0; color: #64748b;">From IP</td>
                            <td style="padding: 4px 0; font-family: ui-monospace, Menlo, monospace;">{{ $audit->ip }}</td>
                        </tr>
                    @endif
                    @if(!empty($g['reasons']))
                        <tr>
                            <td style="padding: 4px 0; color: #64748b; vertical-align: top;">Why flagged</td>
                            <td style="padding: 4px 0;">
                                @foreach($g['reasons'] as $reason)
                                    <span style="display:inline-block; padding: 2px 6px; margin: 0 4px 4px 0; border-radius: 6px; background:#fef2f2; color:#b91c1c; font-size:12px; font-family: ui-monospace, Menlo, monospace;">{{ $reason }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 4px 0; color: #64748b;">Audit row</td>
                        <td style="padding: 4px 0; font-family: ui-monospace, Menlo, monospace;">
                            @if($g['auditUrl'] !== '')
                                <a href="{{ $g['auditUrl'] }}" style="color:#b91c1c; text-decoration: none;">#{{ $audit->id }}</a>
                            @else
                                #{{ $audit->id }}
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach

        @if($listUrl !== '')
            <div style="text-align: center; margin: 28px 0;">
                <a href="{{ $listUrl }}"
                   style="background-color: #b91c1c; color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">
                    Review in audit timeline
                </a>
            </div>

            <p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 0 0 8px;">
                @if(count($grants) === 1)
                    The button opens the user-access "Recent role changes"
                    panel so you can compare this grant against neighbouring
                    revokes. Use the row link above to jump straight to
                    audit row #{{ $grants[0]['audit']->id }}.
                @else
                    The button opens the user-access "Recent role changes"
                    panel so you can review all {{ count($grants) }} grants
                    side-by-side with neighbouring revokes. Each row link
                    above jumps straight to that specific audit entry.
                @endif
            </p>
        @endif

        <p style="color: #94a3b8; font-size: 12px; line-height: 1.6; margin: 24px 0 0;">
            You're receiving this because your address is listed in
            <code style="font-family: ui-monospace, Menlo, monospace;">PLATFORM_ROLE_ALERT_RECIPIENTS</code>
            or your account holds <code>user.ops_alerts.receive</code>.
        </p>
    </div>
</body>
</html>
