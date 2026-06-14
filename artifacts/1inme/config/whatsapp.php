<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (Meta) — OTP delivery
    |--------------------------------------------------------------------------
    |
    | Credentials for sending one-time codes over WhatsApp via the Meta
    | WhatsApp Cloud API. When any of phone_number_id / access_token is
    | absent the provider runs in "preview" mode: OtpService::sendWhatsApp()
    | logs the code instead of calling Meta, so the whole flow stays
    | demonstrable in development without live credentials.
    |
    | Admin-facing controls (enable mobile login, allowed country codes)
    | live in AppSetting and are read through App\Modules\Common\Support\
    | AuthMethods — NOT here. This file only holds the delivery credentials.
    |
    */

    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

    // Name of the approved WhatsApp message template used to deliver the
    // code. The template must contain a single body parameter (the code)
    // and, typically, a one-tap "copy code" button parameter.
    'template_name' => env('WHATSAPP_TEMPLATE_NAME', 'otp_code'),

    'template_language' => env('WHATSAPP_TEMPLATE_LANG', 'en_US'),

    // Graph API version used to build the messages endpoint URL.
    'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v21.0'),
];
