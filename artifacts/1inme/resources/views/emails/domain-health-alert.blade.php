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

            @if($type === 'custom_domain_unverified')
                <h1 style="font-size:20px; color:#b91c1c; margin:0 0 12px 0;">
                    {{ $domain->domain }} has been unverified
                </h1>
                <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                    DNS for <strong>{{ $domain->domain }}</strong> has stopped pointing at Sayzio for more than {{ $payload['grace_hours'] ?? 168 }} hours, so we've automatically unverified the domain. Short links and Link in Bio pages bound to it will stop being served on this host until you fix DNS and re-verify.
                </p>
                <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                    The domain is still locked to your account on Sayzio, so no one else can claim it while you sort out DNS.
                </p>
            @else
                <h1 style="font-size:20px; color:#1e293b; margin:0 0 12px 0;">
                    DNS for {{ $domain->domain }} stopped pointing at Sayzio
                </h1>
                <p style="font-size:14px; color:#334155; line-height:1.6; margin:0 0 16px 0;">
                    Our background check noticed that <strong>{{ $domain->domain }}</strong> no longer resolves to our infrastructure. If this isn't fixed within {{ $payload['grace_hours'] ?? 168 }} hours of the first drift event, we'll automatically unverify the domain so you can recover it later, but in the meantime, traffic to this host may not resolve correctly.
                </p>
            @endif

            <div style="font-size:12px; color:#475569; background:#f1f5f9; padding:12px 14px; border-radius:8px; margin:0 0 20px 0;">
                <div style="font-weight:600; color:#1e293b; margin-bottom:6px;">Restore the CNAME at your DNS provider</div>
                <div style="font-family:Menlo, monospace; line-height:1.7;">
                    Type:&nbsp;&nbsp;<strong>CNAME</strong><br>
                    Name:&nbsp;&nbsp;<strong>{{ $domain->domain }}</strong><br>
                    Target:&nbsp;<strong>{{ $payload['expected_cname'] ?? '' }}</strong><br>
                    TTL:&nbsp;&nbsp;&nbsp;<strong>300</strong> (or Auto)
                </div>
                @if(!empty($payload['observed_cname']))
                    <div style="margin-top:8px; color:#64748b;">
                        Currently resolving to: <span style="font-family:Menlo, monospace;">{{ $payload['observed_cname'] }}</span>
                    </div>
                @endif
            </div>

            <p style="margin:0 0 24px 0;">
                <a href="{{ \App\Modules\Common\Support\PlatformHosts::outboundUrl($payload['target_url'] ?? url('/')) }}"
                   style="display:inline-block; background-color:#2563eb; color:#ffffff; padding:12px 22px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600;">
                    Open domain settings
                </a>
            </p>

            <p style="color:#94a3b8; font-size:12px; line-height:1.6; margin:24px 0 0 0; border-top:1px solid #e2e8f0; padding-top:20px;">
                You're receiving this because you're the owner of a custom domain on Sayzio. We send at most one of these per domain per 24 hours while DNS is broken. Manage notification preferences in your profile settings.
            </p>
        </div>
    </div>
</body>
</html>
