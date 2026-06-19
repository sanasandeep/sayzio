<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 24px; color: #1e293b; margin-bottom: 8px;">1INME</h1>
        <h2 style="font-size: 18px; color: #334155; margin-bottom: 20px;">Your email isn't verified yet</h2>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Hi {{ $user->name }},<br><br>
            We noticed your 1INME email address still hasn't been verified. Verifying it keeps your account secure and makes sure you never miss important updates. It only takes a moment — just click the button below.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}" style="background-color: #2563eb; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">Verify Email</a>
        </div>
        <p style="color: #94a3b8; font-size: 12px;">If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ $verificationUrl }}" style="color: #2563eb; word-break: break-all;">{{ $verificationUrl }}</a>
        </p>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 28px 0 16px;">
        <p style="color: #94a3b8; font-size: 12px; line-height: 1.6;">
            If you didn't create a 1INME account, you can safely ignore this email.<br>
            Don't want these reminders? <a href="{{ $unsubscribeUrl }}" style="color: #64748b;">Unsubscribe</a>.
        </p>
    </div>
</body>
</html>
