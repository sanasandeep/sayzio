<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Preview checkout
    |--------------------------------------------------------------------------
    |
    | The preview checkout flow (/checkout/preview + /checkout/return) simulates
    | a successful payment WITHOUT collecting money or verifying a real provider
    | charge. It exists only so the monetization demo works before a real payout
    | provider is wired up.
    |
    | Because it grants paid entitlements (subscriptions, unlocks, tips, product
    | orders, paid forms, event tickets) on token possession alone, it MUST NOT
    | be reachable in production — otherwise anyone could unlock paid content for
    | free. Leave this null to auto-disable in production while keeping it enabled
    | for local/testing/staging. Set an explicit boolean to force a choice (e.g.
    | true on an internal demo host that deliberately runs without real gateways).
    |
    */
    'allow_preview_checkout' => env('MONETIZATION_ALLOW_PREVIEW_CHECKOUT', null),
];
