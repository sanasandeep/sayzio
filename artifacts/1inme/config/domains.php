<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Custom-domain DNS health
    |--------------------------------------------------------------------------
    |
    | drift_grace_hours — how long a verified custom domain is allowed to
    | drift (CNAME no longer pointing at us) before the platform auto-
    | unverifies it. The domain row is kept (preserving the unique-domain
    | claim lock) so the original creator can fix DNS and re-verify later.
    |
    */
    'drift_grace_hours' => (int) env('DOMAIN_DRIFT_GRACE_HOURS', 168),

    /*
    |--------------------------------------------------------------------------
    | Automatic SSL certificate issuance (EC2 / self-hosted only)
    |--------------------------------------------------------------------------
    |
    | When enabled, the scheduled `domains:issue-certificates` command shells
    | out to `ssl.command` for every verified domain that doesn't have a
    | certificate yet (customer custom domains AND admin global domains).
    | The default command matches the EC2 kit's root helper installed by
    | deploy/ec2/README.md Step 7 (sudoers-whitelisted, runs certbot webroot
    | issuance + writes the per-domain nginx vhost + reloads nginx).
    |
    | Disabled by default: on Replit there is no certbot/nginx — TLS is
    | terminated by the platform proxy. Only set SSL_AUTO_ISSUE=true on the
    | EC2 deployment after completing the README's Step 7 setup.
    |
    */
    'ssl' => [
        'auto_issue' => (bool) env('SSL_AUTO_ISSUE', false),

        // Executable + fixed args; the domain (and optional account email)
        // are appended as separate argv entries — never shell-interpolated.
        'command' => env('SSL_ISSUE_COMMAND', 'sudo -n /usr/local/sbin/sayzio-issue-cert'),

        // Optional Let's Encrypt account email, passed to the helper (only
        // needed if no certbot account exists on the box yet).
        'certbot_email' => env('SSL_CERTBOT_EMAIL'),

        // Max seconds one issuance run may take (DNS + ACME round trips).
        'timeout' => (int) env('SSL_ISSUE_TIMEOUT', 300),

        // Minimum hours between attempts for the same failing domain.
        'retry_hours' => (int) env('SSL_RETRY_HOURS', 1),

        // Alert admins once a domain has failed this many consecutive
        // attempts (DNS may legitimately still be propagating early on)...
        'alert_after_attempts' => (int) env('SSL_ALERT_AFTER_ATTEMPTS', 3),

        // ...and re-alert at most once per this many hours while it keeps failing.
        'alert_cooldown_hours' => (int) env('SSL_ALERT_COOLDOWN_HOURS', 24),
    ],
];
