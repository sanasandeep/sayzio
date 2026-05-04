<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>2FA required for {{ $workspace->name }}</title></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f6f7fb; margin:0; padding:24px; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; padding:28px;">
        <tr><td>
            <h2 style="margin:0 0 12px; font-size:20px;">Two-factor authentication is now required</h2>
            <p>Hi {{ $memberName }},</p>
            <p>The owner of <strong>{{ $workspace->name }}</strong> turned on a workspace-wide 2FA policy. From now on, every member needs an authenticator app (Google Authenticator, 1Password, Authy, etc.) to sign in.</p>

            @if($alreadyEnrolled)
                <p style="background:#ecfdf5; border:1px solid #a7f3d0; padding:12px; border-radius:8px; color:#065f46;">
                    Good news — you already have 2FA enabled, so nothing changes for you.
                </p>
            @else
                @if($graceDeadline)
                    <p>You have until <strong>{{ $graceDeadline }}</strong> to enroll. After that, you'll be walked through setup the next time you sign in and won't be able to access {{ $workspace->name }} until enrollment is complete.</p>
                @else
                    <p>Enrollment is required immediately — you'll be walked through setup the next time you sign in.</p>
                @endif

                <p style="text-align:center; margin:24px 0;">
                    <a href="{{ $setupUrl }}" style="background:#7c3aed; color:#ffffff; text-decoration:none; padding:12px 22px; border-radius:8px; font-weight:600; display:inline-block;">Set up 2FA now</a>
                </p>
            @endif

            <p style="font-size:12px; color:#6b7280; margin-top:24px;">If you weren't expecting this email, reply to your workspace owner — they made the change.</p>
        </td></tr>
    </table>
</body>
</html>
