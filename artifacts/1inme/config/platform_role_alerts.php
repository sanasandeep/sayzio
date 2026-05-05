<?php

/*
|--------------------------------------------------------------------------
| Platform-role alert configuration
|--------------------------------------------------------------------------
|
| Decides which user-pool role grants are considered "platform-admin"
| level and should fire an ops alert email when attached. Mirrors the
| `SensitiveActionLogger` workspace pattern but lives at the platform
| (cross-workspace) tier.
|
| A role qualifies as sensitive if EITHER its slug appears in
| `sensitive_role_slugs` OR any of its attached permissions has a slug
| in `sensitive_permission_slugs`. The two lists are union'ed so a
| custom role with admin-level permissions is still caught even if
| ops haven't enumerated it explicitly.
|
| Recipients: if `recipient_emails` is non-empty it wins outright;
| otherwise the alert fans out to every user holding the
| `user.ops_alerts.receive` permission (same fallback the site-assistant
| cut-off and image re-optimisation alerts use).
|
*/

return [

    'sensitive_role_slugs' => array_values(array_filter(array_map(
        fn ($v) => trim((string) $v),
        explode(',', (string) env('PLATFORM_ROLE_ALERT_ROLES', 'user-admin'))
    ), fn ($v) => $v !== '')),

    'sensitive_permission_slugs' => array_values(array_filter(array_map(
        fn ($v) => trim((string) $v),
        explode(',', (string) env(
            'PLATFORM_ROLE_ALERT_PERMISSIONS',
            'user.platform.admin,user.workspaces.access_any,user.subscriptions.activate_manually,user.roles.manage'
        ))
    ), fn ($v) => $v !== '')),

    'recipient_emails' => array_values(array_filter(array_map(
        fn ($v) => strtolower(trim((string) $v)),
        explode(',', (string) env('PLATFORM_ROLE_ALERT_RECIPIENTS', ''))
    ), fn ($e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))),

];
