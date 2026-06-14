<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Services\Billing\ClientInvoiceService;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generic gateway webhook router. One URL per gateway slug
 * (`/webhooks/{gateway}`) — this delegates to the matching adapter for
 * signature verification + payload parsing, then runs the activation
 * pipeline. Idempotent on (gateway, gateway_ref): duplicate deliveries
 * short-circuit on the payment_attempts unique index, and the
 * ActivateSubscription action itself is re-entrant.
 */
class WebhookController extends Controller
{
    public function handle(string $gateway, Request $request, GatewayManager $gm, ActivateSubscription $activator)
    {
        try {
            $adapter = $gm->for($gateway);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'unknown gateway'], 404);
        }

        try {
            if (!$adapter->verifyWebhook($request)) {
                return response()->json(['error' => 'invalid signature'], 400);
            }
            $event = $adapter->parseEvent($request);
        } catch (NotImplementedException $e) {
            // Accept-and-ignore so the gateway doesn't retry forever while
            // the real adapter is still being built in a later task.
            Log::info("Ignoring {$gateway} webhook (adapter stubbed)");
            return response()->json(['ok' => true, 'stubbed' => true], 202);
        }

        $ref = (string) ($event['gateway_ref'] ?? '');
        if ($ref === '') {
            return response()->json(['error' => 'missing gateway_ref'], 400);
        }

        // The invoice MUST exist before we create a PaymentAttempt row
        // (invoice_id is an FK). If the gateway sent us a ref for an
        // invoice we don't know, log + accept so it doesn't retry.
        $invoiceId = (int) ($event['invoice_id'] ?? 0);
        $invoice = $invoiceId ? Invoice::find($invoiceId) : null;
        if (!$invoice) {
            Log::warning('Webhook references unknown invoice', [
                'gateway' => $gateway, 'gateway_ref' => $ref, 'invoice_id' => $invoiceId,
            ]);
            return response()->json(['ok' => true, 'note' => 'invoice not found'], 202);
        }

        // Idempotency guard: one PaymentAttempt per (gateway, gateway_ref).
        // Catch the unique-constraint race on concurrent deliveries and
        // re-fetch so we converge on the existing row.
        try {
            $attempt = DB::transaction(function () use ($gateway, $ref, $event, $invoice) {
                return PaymentAttempt::firstOrCreate(
                    ['gateway' => $gateway, 'gateway_ref' => $ref],
                    [
                        'invoice_id'            => $invoice->id,
                        'status'                => 'initiated',
                        'raw_response'          => $event['raw'] ?? [],
                        'signature_verified_at' => now(),
                    ]
                );
            });
        } catch (\Illuminate\Database\QueryException $e) {
            $attempt = PaymentAttempt::where('gateway', $gateway)->where('gateway_ref', $ref)->first();
            if (!$attempt) throw $e;
        }

        $type = (string) ($event['type'] ?? '');
        if ($type === 'payment.succeeded') {
            $attempt->update(['status' => 'succeeded']);
            try {
                // Client invoices (kanban) follow a different post-payment
                // pipeline than subscription invoices: no plan activation,
                // just sync the originating cards + flip status.
                if (($invoice->kind ?? 'subscription') === 'client') {
                    app(ClientInvoiceService::class)->markPaid($invoice, $gateway, $ref);
                } else {
                    $activator->run($invoice, $gateway, $ref);
                }
            } catch (\Throwable $e) {
                // The gateway confirmed the money moved, but turning that
                // payment into a granted plan / credited coins threw. This
                // is a high-signal ops event: a customer may have paid
                // without getting what they bought. Alert the team, then
                // re-throw so the gateway still sees a 5xx and retries.
                $this->alertPaymentActivationFailed($gateway, $invoice, $ref, $e);
                throw $e;
            }
            return response()->json(['ok' => true], 200);
        }
        if ($type === 'payment.failed') {
            $attempt->update(['status' => 'failed']);
            return response()->json(['ok' => true], 200);
        }
        $attempt->update(['status' => 'requires_review']);
        return response()->json(['ok' => true], 202);
    }

    /**
     * Best-effort team alert when a confirmed payment fails to activate.
     * Wrapped so a dead webhook can never mask the underlying error we're
     * about to re-throw.
     */
    private function alertPaymentActivationFailed(string $gateway, Invoice $invoice, string $ref, \Throwable $e): void
    {
        try {
            app(\App\Modules\Common\Services\NotificationService::class)->systemAlert(
                'Payment activation failed after a successful charge',
                "A {$gateway} payment succeeded but post-payment processing threw for invoice {$invoice->number}."
                    . ' The customer may have paid without receiving their plan or coins — needs a human.',
                'critical',
                [
                    'gateway'     => $gateway,
                    'invoice'     => $invoice->number,
                    'invoice_id'  => $invoice->id,
                    'gateway_ref' => $ref,
                    'error'       => \Illuminate\Support\Str::limit($e->getMessage(), 300),
                ],
                \App\Services\Integrations\IntegrationKeySettings::ALERT_CATEGORY_PAYMENT,
            );
        } catch (\Throwable $alertError) {
            Log::warning('Failed to dispatch payment-activation alert: ' . $alertError->getMessage());
        }
    }
}
