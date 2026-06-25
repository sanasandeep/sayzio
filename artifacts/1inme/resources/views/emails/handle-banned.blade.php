<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; background-color:#f8fafc;">
    <div style="max-width:560px; margin:0 auto; padding:32px 16px;">
        <div style="background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 1px 3px rgba(0,0,0,0.08);">
            <div style="text-align:left; margin-bottom:24px;">
                @include('common.partials.brand-logo-email')
            </div>

            <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                Hi {{ $userName }}, please pick a new handle
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                Your current Sayzio handle <strong>&#64;{{ $handle }}</strong> is no longer available
                because it now matches an entry on our reserved/banned names list. To keep your
                profile working, please choose a new handle in your profile settings.
            </p>

            <p style="margin:0 0 24px 0;">
                <a href="{{ $profileUrl }}"
                   style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Change my handle
                </a>
            </p>

            <p style="font-size:13px; color:#64748b; line-height:1.6; margin:0;">
                Picking a new handle takes less than a minute. Existing links to your old handle
                may stop working once it's reassigned, so we recommend updating it sooner rather
                than later.
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this because an administrator flagged your handle as needing a
                rename. This is an account-required notice, so it can't be unsubscribed from, but
                we send it at most once per day per account.
            </p>
        </div>
    </div>
</body>
</html>
