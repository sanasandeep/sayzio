<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <div style="margin-bottom: 8px;">@include('common.partials.brand-logo-email')</div>
        <h2 style="font-size: 18px; color: #334155; margin-bottom: 20px;">Verify Your Email</h2>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Hi {{ $user->name }},<br><br>
            Welcome to Sayzio! Please click the button below to verify your email address.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $verificationUrl }}" style="background-color: #2563eb; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">Verify Email</a>
        </div>
        <p style="color: #94a3b8; font-size: 12px;">If you didn't create an account, you can safely ignore this email.</p>
    </div>
</body>
</html>
