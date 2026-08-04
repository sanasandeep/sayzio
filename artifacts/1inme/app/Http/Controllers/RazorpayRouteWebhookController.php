<?php

namespace App\Http\Controllers;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Services\CreatorPayouts\Adapters\RazorpayRouteAdapter;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay webhooks for the creator-payout (Route) product — distinct
 * from the plan-billing `/webhooks/razorpay` gateway handled by
 * WebhookController/GatewayManager.
 *
 * CSRF-exempt (routes/webhooks.php lives under the `webhooks/*` CSRF
 * exemption); authenticity is enforced by verifying the
 * `X-Razorpay-Signature` HMAC-SHA256 over the raw body with
 * RAZORPAY_WEBHOOK_SECRET (falling back to RAZORPAY_KEY_SECRET).
 *
 * Handled events:
 *   - payment.captured  → settle the MonetizationCheckout record
 *                         (idempotent: re-deliveries are no-ops)
 *   - payment.failed    → logged; the pending row simply never settles
 *   - account.*         → flip the connection's status / payouts_enabled
 *                         / charges_enabled from the linked-account state
 */
class RazorpayRouteWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = env('RAZORPAY_WEBHOOK_SECRET') ?: env('RAZORPAY_KEY_SECRET');
        if (!$secret) {
            return response()->json(['error' => 'Razorpay is not configured.'], 503);
        }

        $signature = (string) $request->header('X-Razorpay-Signature', '');
        $expected  = hash_hmac('sha256', $request->getContent(), (string) $secret);
        if ($signature === '' || !hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $event = (string) $request->input('event', '');

        if (str_starts_with($event, 'payment.')) {
            return $this->handlePayment($event, $request);
        }
        if (str_starts_with($event, 'account.')) {
            return $this->handleAccount($event, $request);
        }

        return response()->json(['status' => 'ignored']);
    }

    protected function handlePayment(string $event, Request $request)
    {
        $payment = (array) $request->input('payload.payment.entity', []);
        $orderId = (string) ($payment['order_id'] ?? '');

        // Resolve the checkout context: prefer the order-id cache written at
        // order creation; fall back to notes copied onto the payment.
        $ctx = $orderId !== '' ? cache()->get(RazorpayRouteAdapter::ORDER_CACHE_PREFIX . $orderId) : null;
        if (!$ctx) {
            $notes = (array) ($payment['notes'] ?? []);
            if (!empty($notes['reference']) && !empty($notes['token'])) {
                $ctx = [
                    'kind'      => (string) ($notes['kind'] ?? 'tip'),
                    'reference' => (string) $notes['reference'],
                    'token'     => (string) $notes['token'],
                ];
            }
        }

        if ($event === 'payment.failed') {
            Log::info('razorpay_route.payment_failed', [
                'payment' => $payment['id'] ?? null,
                'order'   => $orderId,
                'reason'  => $payment['error_description'] ?? null,
            ]);
            return response()->json(['status' => 'ok']);
        }

        if ($event !== 'payment.captured') {
            return response()->json(['status' => 'ignored']);
        }

        if (!$ctx) {
            Log::warning('razorpay_route.captured_without_context', [
                'payment' => $payment['id'] ?? null,
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

    protected function handleAccount(string $event, Request $request)
    {
        $account   = (array) $request->input('payload.account.entity', []);
        $accountId = (string) ($account['id'] ?? '');
        if ($accountId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $connection = CreatorPaymentConnection::where('provider', 'razorpay')
            ->where('account_id', $accountId)
            ->first();
        if (!$connection) {
            return response()->json(['status' => 'unmatched']);
        }

        // Prefer the entity's own status field; fall back to the event verb
        // (account.activated / account.suspended / …).
        $status = (string) ($account['status'] ?? substr($event, strlen('account.')));
        RazorpayRouteAdapter::applyAccountStatus($connection, $status);

        return response()->json(['status' => 'ok']);
    }
}
