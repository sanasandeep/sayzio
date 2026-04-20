<?php

/**
 * Country (ISO 3166-1 alpha-2, uppercase) → billing currency (ISO 4217).
 *
 * Anything not listed here resolves to the default currency (USD) at the
 * PricingResolver layer. Add more rows over time without touching code.
 */

return [
    'IN' => 'INR',
];
