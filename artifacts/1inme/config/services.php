<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

    // Google Business Profile reviews via the Places Details API. Read by
    // GoogleReviewsAdapter; absent key = transparent preview mode.
    'google_places' => [
        'api_key' => env('GOOGLE_PLACES_API_KEY'),
    ],

    // Trustpilot public Business Unit reviews API. Read by TrustpilotAdapter;
    // absent key = transparent preview mode.
    'trustpilot' => [
        'api_key' => env('TRUSTPILOT_API_KEY'),
    ],

    // Google Custom Search JSON API (Programmable Search Engine) — image
    // search for the AI biolink builder. Read by GoogleImageSearchService;
    // absent key/engine = preview mode (feature hidden gracefully).
    'google_cse' => [
        'api_key'   => env('GOOGLE_CSE_API_KEY'),
        'engine_id' => env('GOOGLE_CSE_ENGINE_ID'),
    ],

    'google_calendar' => [
        'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    ],

    'google_contacts' => [
        'client_id'     => env('GOOGLE_CONTACTS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CONTACTS_CLIENT_SECRET'),
    ],

    'microsoft_calendar' => [
        'client_id'     => env('MICROSOFT_CALENDAR_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET'),
        'tenant'        => env('MICROSOFT_CALENDAR_TENANT', 'common'),
    ],

    // Replicate — hosted QR-ControlNet model that weaves a scannable QR
    // into AI-generated artwork ("AI Artistic QR" in QR Studio). The token
    // is also admin-configurable (encrypted) via AiEngineSettings, which
    // falls back to this env value when no admin key is stored. Absent
    // token = transparent preview / disabled mode. `qr_model` lets ops pin
    // a specific Replicate model without a code change.
    'replicate' => [
        'api_token' => env('REPLICATE_API_TOKEN'),
        'qr_model'  => env('REPLICATE_QR_MODEL', 'zylim0702/qr_code_controlnet'),
    ],

    // GitHub mirror — code is pushed to this repo after each publish using
    // a fine-grained personal access token (GITHUB_TOKEN secret). The token
    // expires (~90-day lifetime); github:check-token probes it daily and
    // alerts ops admins before pushes silently start failing.
    'github' => [
        'token' => env('GITHUB_TOKEN'),
        'repo'  => env('GITHUB_REPO', 'sanasandeep/sayzio'),
    ],
];
