<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="margin-bottom: 8px;">@include('common.partials.brand-logo-email')</div>
        <h2 style="font-size: 18px; color: #334155; margin-bottom: 20px;">Keep your free Starter plan</h2>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Hi {{ $user->name }},<br><br>
            Your free year on the Sayzio <strong>Starter</strong> plan
            @if($endsAt)
                wraps up on <strong>{{ $endsAt->format('F j, Y') }}</strong>.
            @else
                is coming up for renewal.
            @endif
            Good news: Starter is free forever — we just like to check in once a year. Renewing keeps your account and all your links exactly as they are. Nothing changes, nothing is locked, and you won't lose anything.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $renewUrl }}" style="background-color: #7c3aed; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">Renew free for another year</a>
        </div>
        <p style="color: #94a3b8; font-size: 12px;">If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $renewUrl }}" style="color: #7c3aed; word-break: break-all;">{{ $renewUrl }}</a>
        </p>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 28px 0 16px;">
        <p style="color: #94a3b8; font-size: 12px; line-height: 1.6;">
            This is just a friendly reminder — your account stays active whether or not you renew. You can manage these reminders in your notification settings.
        </p>
    </div>
</body>
</html>
