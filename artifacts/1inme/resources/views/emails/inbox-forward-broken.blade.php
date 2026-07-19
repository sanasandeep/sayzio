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
                Hi {{ $userName }}, your forwarding rule "{{ $destination->label }}" keeps failing
            </h1>

            <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                We tried to deliver new inbox events to your
                @if($isWebhook) webhook @else email forwarding address @endif
                <strong style="word-break:break-all;">{{ $destination->target }}</strong>
                and it didn't work. The deliveries are being parked as failed in your dashboard, which means new
                form submissions, subscribers and leads aren't reaching this destination.
            </p>

            @if($deadCount >= 1)
                <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                    In the last {{ $lookbackHours }} hours, <strong>{{ $deadCount }}</strong>
                    {{ $deadCount === 1 ? 'delivery has' : 'deliveries have' }} given up after the maximum number of retries.
                </p>
            @endif

            @if(!empty($reason))
                <p style="font-size:12px; color:#64748b; line-height:1.5; margin:0 0 20px 0; background:#f1f5f9; padding:10px 12px; border-radius:8px; word-break:break-word;">
                    <strong style="color:#475569;">Last error:</strong> {{ \Illuminate\Support\Str::limit($reason, 240) }}
                </p>
            @endif

            <p style="font-size:13px; color:#475569; line-height:1.6; margin:0 0 8px 0;"><strong>Common causes:</strong></p>
            <ul style="font-size:13px; color:#475569; line-height:1.6; margin:0 0 20px 18px; padding:0;">
                @if($isWebhook)
                    <li>The webhook URL is offline or returning a non-2xx status.</li>
                    <li>An auth header (API key / token) on the receiving service was rotated or revoked.</li>
                    <li>The endpoint moved to a new path or domain.</li>
                @else
                    <li>The mailbox is full, suspended, or no longer exists.</li>
                    <li>The receiving server is rejecting our messages as spam.</li>
                    <li>The address has a typo; double-check spelling and domain.</li>
                @endif
            </ul>

            <p style="margin:0 0 24px 0;">
                <a href="{{ $rulesUrl }}"
                   style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Open forwarding rules
                </a>
            </p>

            <p style="font-size:13px; color:#64748b; line-height:1.6; margin:0;">
                Once you've fixed the destination, you can re-run any failed deliveries from the same page; nothing is lost in the meantime.
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this because a forwarding rule on your Sayzio inbox is failing.
                We send at most one of these per destination per day, and we'll stop as soon as deliveries start succeeding again.
            </p>
        </div>
    </div>
</body>
</html>
