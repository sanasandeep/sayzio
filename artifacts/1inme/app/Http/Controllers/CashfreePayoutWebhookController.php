<?php

namespace App\Http\Controllers;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Services\CreatorPayouts\Adapters\CashfreeAdapter;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Cashfree webhooks for the creator-payout (Easy Split) product —
 * distinct from the plan-billing `/webhooks/cashfree` gateway handled
 * by WebhookController/GatewayManager.
 *
 * CSRF-exempt (routes/webhooks.php lives under the `webhooks/*` CSRF
 * exemption); authenticity is enforced by recomputing the
 * `x-webhook-signature` header = Base64(HMAC-SHA256(
 * x-webhook-timestamp . rawBody, secret)) with a 5-minute timestamp
 * tolerance, using CASHFREE_WEBHOOK_SECRET (falling back to
 * CASHFREE_SECRET_KEY per Cashfree's PG v2 spec).
 *
 * Handled events:
 *   - PAYMENT_SUCCESS_WEBHOOK → settle the MonetizationCheckout record
 *                               (idempotent: re-deliveries are no-ops)
 *   - PAYMENT_FAILED_WEBHOOK  → logged; the pending row never settles
 *   - VENDOR_* / vendor payloads → flip the connection's status /
 *                               payouts_enabled from the vendor state
 */
class CashfreePayoutWebhookController extends Controller
{
    protected const SIG_TOLERANCE_SECONDS = 300;

    public function handle(Request $request)
    {
        $secret = env('CASHFREE_WEBHOOK_SECRET') ?: env('CASHFREE_SECRET_KEY');
        if (!$secret) {
            return response()->json(['error' => 'Cashfree is not configured.'], 503);
        }

        if (!$this->verifySignature($request, (string) $secret)) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $type = (string) $request->input('type', '');

        if ($type === 'PAYMENT_SUCCESS_WEBHOOK' || $type === 'PAYMENT_FAILED_WEBHOOK') {
            return $this->handlePayment($type, $request);
        }
        if (str_starts_with($type, 'VENDOR') || $request->has('data.vendor')) {
            return $this->handleVendor($request);
        }

        return response()->json(['status' => 'ignored']);
    }

    protected function verifySignature(Request $request, string $secret): bool
    {
        $sig       = (string) $request->header('x-webhook-signature', '');
        $timestamp = (string) $request->header('x-webhook-timestamp', '');
        if ($sig === '' || $timestamp === '') return false;

        $ts = (int) $timestamp;
        if ($ts <= 0) return false;
        // Cashfree timestamps are in milliseconds. Accept a 5 min window.
        $tsSec = $ts > 1e12 ? (int) floor($ts / 1000) : $ts;
        if (abs(time() - $tsSec) > self::SIG_TOLERANCE_SECONDS) return false;

        $expected = base64_encode(hash_hmac('sha256', $timestamp . $request->getContent(), $secret, true));
        return hash_equals($expected, $sig);
    }

    protected function handlePayment(string $type, Request $request)
    {
        $order   = (array) $request->input('data.order', []);
        $payment = (array) $request->input('data.payment', []);
        $orderId = (string) ($order['order_id'] ?? '');

        // Resolve the checkout context: prefer the order-id cache written
        // at order creation; fall back to the order_tags Cashfree echoes.
        $ctx = $orderId !== '' ? cache()->get(CashfreeAdapter::ORDER_CACHE_PREFIX . $orderId) : null;
        if (!$ctx) {
            $tags = (array) ($order['order_tags'] ?? $request->input('data.order_tags', []));
            if (!empty($tags['reference']) && !empty($tags['token'])) {
                $ctx = [
                    'kind'      => (string) ($tags['kind'] ?? 'tip'),
                    'reference' => (string) $tags['reference'],
                    'token'     => (string) $tags['token'],
                ];
            }
        }

        if ($type === 'PAYMENT_FAILED_WEBHOOK') {
            Log::info('cashfree_payouts.payment_failed', [
                'payment' => $payment['cf_payment_id'] ?? null,
                'order'   => $orderId,
                'reason'  => $payment['payment_message'] ?? null,
            ]);
            return response()->json(['status' => 'ok']);
        }

        if (!$ctx) {
            Log::warning('cashfree_payouts.success_without_context', [
                'payment' => $payment['cf_payment_id'] ?? null,
                'order'   => $orderId,
            ]);
            return response()->json(['status' => 'unmatched']);
        }

        $result = app(MonetizationCheckout::class)->settleFromProvider(
            (string) $ctx['kind'],
            (string) $ctx['reference'],
            (string) $ctx['token'],
        );

        // null → already settled (webhook re-delivery) or expired context.
        return response()->json(['status' => $result ? 'settled' : 'already_settled']);
    }

    protected function handleVendor(Request $request)
    {
        $vendor   = (array) $request->input('data.vendor', []);
        $vendorId = (string) ($vendor['vendor_id'] ?? '');
        if ($vendorId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $connection = CreatorPaymentConnection::where('provider', 'cashfree')
            ->where('account_id', $vendorId)
            ->first();
        if (!$connection) {
            return response()->json(['status' => 'unmatched']);
        }

        $status = (string) ($vendor['status'] ?? '');
        if ($status !== '') {
            CashfreeAdapter::applyVendorStatus($connection, $status);
        }

        return response()->json(['status' => 'ok']);
    }
}
