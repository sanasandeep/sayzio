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
// Inbox 2.0 inbound webhook. Accepts normalised social DM payloads
// (Instagram/TikTok/X via the platform OAuth proxy) and forwarded email
// (Mailgun / Postmark / Cloudflare inbound parsers / Zapier) into the
// unified inbox. Auth is the per-workspace `inbox_inbound_token` and an
// optional HMAC `X-Inbox-Signature` header.
Route::post('/webhooks/inbox/{token}', [\App\Modules\User\Controllers\InboxInboundController::class, 'ingest'])
    ->where('token', '[A-Za-z0-9]{20,80}')
    ->name('webhooks.inbox.ingest');

// Carbon offset provider webhooks (Cloverly, Patch, …). Signature
// verification lives in each adapter; the controller refuses unknown
// providers with a 404 so probing returns no information.
Route::post('/webhooks/carbon/{provider}', [\App\Modules\Common\Controllers\CarbonPublicController::class, 'webhook'])
    ->where('provider', '[a-z0-9_-]+')
    ->name('webhooks.carbon');

Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])
    ->where('gateway', '[a-z0-9_-]+')
    ->name('webhooks.handle');
