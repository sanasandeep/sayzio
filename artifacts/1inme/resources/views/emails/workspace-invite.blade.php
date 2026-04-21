<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 24px; color: #1e293b; margin-bottom: 8px;">{{ config('app.name') }}</h1>
        <h2 style="font-size: 18px; color: #334155; margin-bottom: 20px;">You've been invited to {{ $workspaceName }}</h2>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            {{ $inviterName }} has invited you to join the
            <strong>{{ $workspaceName }}</strong> workspace on {{ config('app.name') }}
            as <strong>{{ $roleLabel }}</strong>.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $acceptUrl }}" style="background-color: #2563eb; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">Accept invite</a>
        </div>
        @if($expiresAt)
            <p style="color: #64748b; font-size: 13px; line-height: 1.6;">
                This invite expires on <strong>{{ $expiresAt }}</strong>.
            </p>
        @endif
        <p style="color: #94a3b8; font-size: 12px; margin-top: 24px;">
            If you weren't expecting this invite, you can safely ignore this email.
        </p>
    </div>
</body>
</html>
