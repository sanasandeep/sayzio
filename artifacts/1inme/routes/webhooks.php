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

// PayU posts the buyer's browser back to this success/failure URL
// (surl/furl) with the signed transaction result. We run the canonical
// webhook pipeline (signature verify + activation, idempotent) and then
// redirect the buyer to their billing page. Declared BEFORE the generic
// catch-all so the two-segment path is not swallowed by `{gateway}`.
Route::post('/webhooks/payumoney/return', [WebhookController::class, 'payumoneyReturn'])
    ->name('webhooks.payumoney.return');

// Two-way WhatsApp AI agent (Task #2759). GET is Meta's verification
// handshake; POST is inbound message delivery (HMAC-verified in the
// controller). Declared BEFORE the generic `webhooks/{gateway}` POST
// catch-all so "whatsapp" isn't swallowed by it.
Route::get('/webhooks/whatsapp', [\App\Modules\Common\Controllers\WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [\App\Modules\Common\Controllers\WhatsAppWebhookController::class, 'ingest'])
    ->name('webhooks.whatsapp.ingest');

// Razorpay Route (creator payouts, Task #6642). Distinct from the
// plan-billing `razorpay` gateway below — this endpoint settles fan
// checkouts (payment.captured) and syncs linked-account status
// (account.*). Signature-verified in the controller. Declared BEFORE
// the generic catch-all so `razorpay-route` isn't swallowed by it.
Route::post('/webhooks/razorpay-route', [\App\Http\Controllers\RazorpayRouteWebhookController::class, 'handle'])
    ->name('webhooks.razorpay-route');

// Cashfree Easy Split (creator payouts, Task #6643). Distinct from the
// plan-billing `cashfree` gateway below — this endpoint settles fan
// checkouts (PAYMENT_SUCCESS_WEBHOOK) and syncs vendor status.
// Signature-verified in the controller. Declared BEFORE the generic
// catch-all so `cashfree-payouts` isn't swallowed by it.
Route::post('/webhooks/cashfree-payouts', [\App\Http\Controllers\CashfreePayoutWebhookController::class, 'handle'])
    ->name('webhooks.cashfree-payouts');

Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle'])
    ->where('gateway', '[a-z0-9_-]+')
    ->name('webhooks.handle');
