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

    'msg91' => [
        'auth_key' => env('MSG91_AUTH_KEY'),
        'sender_id' => env('MSG91_SENDER_ID', '1INME'),
        'route' => env('MSG91_ROUTE', '4'),
    ],

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
    ],

    'google_calendar' => [
        'client_id'     => env('GOOGLE_CALENDAR_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET'),
    ],

    'microsoft_calendar' => [
        'client_id'     => env('MICROSOFT_CALENDAR_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CALENDAR_CLIENT_SECRET'),
        'tenant'        => env('MICROSOFT_CALENDAR_TENANT', 'common'),
    ],
];
