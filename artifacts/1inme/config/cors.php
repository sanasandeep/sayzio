<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The site-wide AI assistant ("Zio Bot") widget is also embedded on the
    | standalone marketing site (1inme.com), which is served from a different
    | origin. Those endpoints must answer cross-origin preflight + requests.
    | Visitors there are always anonymous (the controller forces the
    | "marketing" surface for unauthenticated callers), so no cookies/credentials
    | are needed and a wildcard origin is safe.
    |
    */

    'paths' => ['assistant/*', 'api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
