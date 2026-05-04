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
];
