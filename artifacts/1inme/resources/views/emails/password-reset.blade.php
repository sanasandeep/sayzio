<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 24px; color: #1e293b; margin-bottom: 8px;">1INME</h1>
        <h2 style="font-size: 18px; color: #334155; margin-bottom: 20px;">Reset Your Password</h2>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Hi {{ $user->name }},<br><br>
            You requested a password reset. Click the button below to set a new password. This link expires in 60 minutes.
        </p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $resetUrl }}" style="background-color: #2563eb; color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">Reset Password</a>
        </div>
        <p style="color: #94a3b8; font-size: 12px;">If you didn't request this, you can safely ignore this email.</p>
    </div>
</body>
</html>
