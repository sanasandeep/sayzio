<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Payment-gateway webhooks. These routes are CSRF-exempt (see the
 * `webhooks/*` entry in bootstrap/app.php) because their authenticity is
 * enforced by per-adapter signature verification, not session cookies.
 *
 * Also includes a generic `webhooks/{anything}` catch-all 404 so scanner
 * traffic doesn't get a 500 when a gateway slug is misconfigured.
 */
Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])
    ->where('gateway', '[a-z0-9_-]+')
    ->name('webhooks.handle');
