<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; background-color: #f8fafc; padding: 40px 0;">
    <div style="max-width: 560px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h1 style="font-size: 20px; color: #1e293b; margin-bottom: 4px;">{{ config('app.name') }}</h1>
        <p style="color: #94a3b8; font-size: 13px; margin-top: 0; margin-bottom: 28px;">Reply from our team</p>

        <div style="margin: 24px 0; padding: 0;">
            {!! nl2br(e($reply_body)) !!}
        </div>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 28px 0;">

        <p style="color: #94a3b8; font-size: 12px; margin: 0;">
            <strong style="color: #64748b;">Original message:</strong>
        </p>
        <blockquote style="margin: 8px 0 0; padding: 12px 16px; border-left: 3px solid #e2e8f0; color: #64748b; font-size: 13px; line-height: 1.6;">
            {!! nl2br(e($original_message)) !!}
        </blockquote>

        <p style="color: #94a3b8; font-size: 12px; margin-top: 32px; margin-bottom: 0;">
            &mdash; The {{ config('app.name') }} team
        </p>
    </div>
</body>
</html>
