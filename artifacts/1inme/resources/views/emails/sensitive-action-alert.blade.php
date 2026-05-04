<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 560px; margin: 0 auto; background: white; border-radius: 12px; padding: 36px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 22px; color: #1e293b; margin: 0 0 6px;">{{ config('app.name') }}</h1>
        <h2 style="font-size: 16px; color: #b91c1c; margin: 0 0 18px;">
            Sensitive action in <strong>{{ $workspace->name }}</strong>
        </h2>

        <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 16px;">
            <strong>{{ $actorName }}</strong> just performed
            <strong>{{ $actionLabel }}</strong> on your workspace.
            If this was you (or a teammate you trust), no action is needed.
        </p>

        <table role="presentation" style="width:100%; font-size: 13px; color: #475569; border-collapse: collapse; margin: 16px 0 24px;">
            <tr>
                <td style="padding: 6px 0; width: 130px; color: #64748b;">When</td>
                <td style="padding: 6px 0;">{{ $event->occurred_at?->toDayDateTimeString() }}</td>
            </tr>
            @if($event->target_label)
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">Target</td>
                    <td style="padding: 6px 0;">{{ $event->target_label }}</td>
                </tr>
            @endif
            @if($event->ip)
                <tr>
                    <td style="padding: 6px 0; color: #64748b;">From IP</td>
                    <td style="padding: 6px 0; font-family: ui-monospace, Menlo, monospace;">{{ $event->ip }}</td>
                </tr>
            @endif
        </table>

        <div style="text-align: center; margin: 28px 0;">
            <a href="{{ $reportUrl }}"
               style="background-color: #b91c1c; color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">
                This wasn't authorised
            </a>
        </div>

        <p style="color: #64748b; font-size: 13px; line-height: 1.6; margin: 0 0 8px;">
            Clicking the button above opens an investigation view where you
            can flag the action, see surrounding activity, and lock things
            down. The link is good for 30 days.
        </p>

        <p style="color: #94a3b8; font-size: 12px; line-height: 1.6; margin: 24px 0 0;">
            You can change which actions notify you on the
            <a href="{{ $auditUrl }}" style="color: #2563eb;">audit log settings page</a>.
        </p>
    </div>
</body>
</html>
