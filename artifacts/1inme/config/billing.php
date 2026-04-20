<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Merchant
    |--------------------------------------------------------------------------
    |
    | The legal entity issuing invoices. Used by the tax engine to decide
    | place-of-supply (intra-state vs inter-state GST), and printed on
    | every invoice PDF. There is intentionally NO admin UI for these in
    | this pass — they are config-managed so changing them requires an
    | environment redeploy (legal/finance approval gate).
    |
    | `gst_state` is a 2-letter Indian state code from
    | `App\Services\TaxCalculator::IN_STATES` (e.g. "MH" for Maharashtra).
    | Used by the place-of-supply rule:
    |   - buyer state == merchant gst_state → CGST + SGST (intra-state)
    |   - buyer state != merchant gst_state → IGST (inter-state)
    | If `country` is not "IN" the gst_state value is ignored.
    |
    */

    'merchant' => [
        'name'     => env('MERCHANT_LEGAL_NAME', '1INME Technologies Pvt. Ltd.'),
        'address'  => env('MERCHANT_ADDRESS', '221B Baker Street, Mumbai, MH 400001, India'),
        'country'  => env('MERCHANT_COUNTRY', 'IN'),
        'gst_state' => env('MERCHANT_GST_STATE', 'MH'),
        'gstin'    => env('MERCHANT_GSTIN', '27AAACO9633K1ZK'),
        'vatin'    => env('MERCHANT_VATIN', null),
        'support_email' => env('MERCHANT_SUPPORT_EMAIL', 'billing@1inme.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Financial year
    |--------------------------------------------------------------------------
    |
    | Indian FY runs Apr-1 to Mar-31 (default). Set `start_month` to 1 for
    | calendar-year jurisdictions. Invoice numbering resets at the start
    | of every FY: e.g. INV/2025-26/00001.
    |
    */

    'financial_year' => [
        'start_month' => (int) env('FY_START_MONTH', 4),
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice numbering
    |--------------------------------------------------------------------------
    */

    'invoice' => [
        'prefix' => env('INVOICE_PREFIX', 'INV'),
        'pad'    => 5,
    ],

];
