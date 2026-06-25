<?php

namespace App\Services\Billing\Adapters;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Services\Billing\NotImplementedException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PayUMoney / PayU India gateway adapter.
 *
 * Strategy:
 *   - Checkout is a classic browser form-POST handoff. We render an
 *     auto-submitting form (view `user.checkout.payumoney`) that POSTs
 *     the buyer to PayU's hosted `/_payment` page with a SHA-512
 *     request hash. PayU has no usable subscription/mandate primitive
 *     here, so EVERY checkout — plan, upgrade, renewal, coin top-up —
 *     is a one-time order. First-cycle plan purchases still activate
 *     the subscription via the normal activation pipeline; automatic
 *     renewals are NOT supported (see chargeRecurring()).
 *
 *   - PayU confirms a payment two ways, both carrying the same field
 *     set and the same reverse SHA-512 hash:
 *       (a) a browser POST to our success/failure URLs (surl/furl),
 *           handled by WebhookController::payumoneyReturn() which runs
 *           the canonical webhook pipeline then redirects the buyer;
 *       (b) an optional server-to-server webhook the merchant points at
 *           `/webhooks/payumoney`.
 *     Both flow through verifyWebhook()/parseEvent(). The browser
 *     return (a) only fires if PayU can redirect the buyer back; the
 *     S2S webhook (b) is the resilient path that still fulfils when the
 *     buyer abandons the browser, so configuring it is recommended.
 *
 *   - Idempotency is keyed on PayU's `mihpayid` (its unique payment id)
 *     returned as gateway_ref from parseEvent(); a duplicate delivery
 *     collides on the payment_attempts (gateway, gateway_ref) unique
 *     index and the router converges.
 *
 *   - We embed the internal invoice id in the PayU `txnid`
 *     (`inv{id}x{hash}`) so both callbacks can resolve the invoice
 *     without a prior lookup. The txnid is deterministic per invoice so
 *     a buyer refreshing the checkout page does not spawn extra PayU
 *     transactions.
 *
 *   - Refunds → PayU merchant web-service `cancel_refund_transaction`.
 *     PayU queues refunds asynchronously, so refund() reports `pending`;
 *     an admin (or a later refund webhook) confirms settlement.
 *
 * Hash formulas (PayU India, no additional charges):
 *   request:  sha512(key|txnid|amount|productinfo|firstname|email
 *                     |udf1..udf10|salt)            — udf* left empty
 *   response: sha512(salt|status|udf10..udf1|email|firstname
 *                     |productinfo|amount|txnid|key)
 *
 * Test vs live differ only by host: test.payu.in vs secure.payu.in
 * (checkout) and test.payu.in vs info.payu.in (merchant web-service).
 */
class PayuAdapter extends AbstractAdapter
{
    public function slug(): string { return 'payumoney'; }
    public function displayName(): string { return 'PayUMoney (cards, UPI, netbanking, wallets)'; }

    /** test|live resolved from the gateway_settings `mode` column. */
    protected function isLive(): bool
    {
        return (string) ($this->settings?->mode ?? 'test') === 'live';
    }

    /** Hosted checkout base host. */
    protected function checkoutBase(): string
    {
        return $this->isLive() ? 'https://secure.payu.in' : 'https://test.payu.in';
    }

    /** Merchant web-service endpoint (refunds, transaction queries). */
    protected function webServiceUrl(): string
    {
        return $this->isLive()
            ? 'https://info.payu.in/merchant/postservice.php?form=2'
            : 'https://test.payu.in/merchant/postservice?form=2';
    }

    // ------------------------------------------------------------------
    //  Checkout handoff
    // ------------------------------------------------------------------

    public function createCheckout(Invoice $invoice): array
    {
        $key  = (string) $this->cred('merchant_key', '');
        $salt = (string) $this->cred('salt', '');
        if ($key === '' || $salt === '') {
            throw new NotImplementedException('PayU credentials are not configured.');
        }

        $currency = strtoupper((string) $invoice->currency);
        $amount   = $this->minorToMoney((int) $invoice->grand_total_minor);
        $txnid    = $this->txnIdFor($invoice);

        $user        = $invoice->user;
        $firstname   = $this->firstName((string) ($user?->name ?? ''));
        $email       = (string) ($user?->email ?? '');
        $phone       = $this->phone((string) ($user?->phone ?? ''));
        $productinfo = 'Invoice ' . $invoice->number;

        $hash = $this->requestHash($key, $salt, $txnid, $amount, $productinfo, $firstname, $email);

        $return = route('webhooks.payumoney.return');

        $fields = [
            'key'         => $key,
            'txnid'       => $txnid,
            'amount'      => $amount,
            'productinfo' => $productinfo,
            'firstname'   => $firstname,
            'email'       => $email,
            'phone'       => $phone,
            'surl'        => $return,
            'furl'        => $return,
            'hash'        => $hash,
        ];

        // Audit-trail row for the handoff. Deterministic key on the txnid
        // so a refresh updates in place rather than exploding the index.
        PaymentAttempt::updateOrCreate(
            ['gateway' => 'payumoney', 'gateway_ref' => 'txn:' . $txnid],
            [
                'invoice_id'   => $invoice->id,
                'status'       => 'initiated',
                'raw_response' => [
                    'kind'     => 'order',
                    'txnid'    => $txnid,
                    'amount'   => (int) $invoice->grand_total_minor,
                    'currency' => $currency,
                    'mode'     => $this->isLive() ? 'live' : 'test',
                ],
            ],
        );

        $invoice->forceFill(['gateway' => 'payumoney'])->save();

        return [
            'kind' => 'view',
            'view' => 'user.checkout.payumoney',
            'data' => [
                'invoice'      => $invoice,
                'action'       => $this->checkoutBase() . '/_payment',
                'fields'       => $fields,
                'amount_minor' => (int) $invoice->grand_total_minor,
                'currency'     => $currency,
            ],
        ];
    }

    // ------------------------------------------------------------------
    //  Webhook / return-callback signature + parsing
    // ------------------------------------------------------------------

    public function verifyWebhook(Request $request): bool
    {
        $key  = (string) $this->cred('merchant_key', '');
        $salt = (string) $this->cred('salt', '');
        if ($salt === '') return false;

        $posted = (string) $request->input('hash', '');
        if ($posted === '') return false;

        // The `key` echoed back must be our merchant key.
        $postedKey = (string) $request->input('key', '');
        if ($key !== '' && !hash_equals($key, $postedKey)) return false;

        $expected = $this->responseHash(
            $salt,
            (string) $request->input('status', ''),
            (string) $request->input('email', ''),
            (string) $request->input('firstname', ''),
            (string) $request->input('productinfo', ''),
            (string) $request->input('amount', ''),
            (string) $request->input('txnid', ''),
            $postedKey !== '' ? $postedKey : $key,
            (string) $request->input('additionalCharges', ''),
        );

        return hash_equals($expected, strtolower($posted));
    }

    public function parseEvent(Request $request): array
    {
        $raw     = $request->all();
        $status  = strtolower((string) $request->input('status', ''));
        $txnid   = (string) $request->input('txnid', '');
        $mihpayid = (string) $request->input('mihpayid', '');
        $amount  = (string) $request->input('amount', '0');

        $invoiceId = $this->invoiceIdFromTxn($txnid);
        $invoice   = $invoiceId ? Invoice::find($invoiceId) : null;

        $type = match ($status) {
            'success' => 'payment.succeeded',
            'failure', 'failed' => 'payment.failed',
            default => 'payment.requires_review',
        };

        return [
            'type'         => $type,
            'invoice_id'   => $invoice ? (int) $invoice->id : null,
            'gateway_ref'  => $mihpayid !== '' ? $mihpayid : ('txn:' . $txnid),
            'amount_minor' => $this->moneyToMinor($amount),
            'currency'     => $invoice ? strtoupper((string) $invoice->currency) : 'INR',
            'raw'          => $raw + ['mihpayid' => $mihpayid, 'txnid' => $txnid],
        ];
    }

    // ------------------------------------------------------------------
    //  Refund
    // ------------------------------------------------------------------

    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        $key  = (string) $this->cred('merchant_key', '');
        $salt = (string) $this->cred('salt', '');
        if ($key === '' || $salt === '') {
            throw new NotImplementedException('PayU credentials are not configured.');
        }

        $mihpayid = $this->lookupPaymentId($invoice);
        if (!$mihpayid) {
            throw new \RuntimeException('No PayU payment id found for invoice ' . $invoice->number);
        }

        $command   = 'cancel_refund_transaction';
        $refundRef = 'rf' . $invoice->id . 't' . substr(md5($invoice->number . '|' . microtime()), 0, 10);
        $amount    = $this->minorToMoney($amountMinor);
        $hash      = hash('sha512', $key . '|' . $command . '|' . $mihpayid . '|' . $salt);

        $res = Http::asForm()->post($this->webServiceUrl(), [
            'key'     => $key,
            'command' => $command,
            'hash'    => $hash,
            'var1'    => $mihpayid,
            'var2'    => $refundRef,
            'var3'    => $amount,
        ]);
        $this->assertOk($res, 'create refund', $invoice);

        $body      = $res->json() ?: [];
        $accepted  = (int) ($body['status'] ?? 0) === 1;
        if (!$accepted) {
            $msg = (string) ($body['msg'] ?? 'PayU refused the refund request.');
            throw new \RuntimeException('PayU refund failed: ' . $msg);
        }

        $gatewayRef = (string) ($body['request_id'] ?? $body['mihpayid'] ?? $refundRef);

        // PayU settles refunds asynchronously; report pending so the
        // RefundService awaits confirmation rather than crediting now.
        return [
            'gateway_ref' => $gatewayRef,
            'status'      => 'pending',
        ];
    }

    // ------------------------------------------------------------------
    //  Recurring (unsupported — surfaced as one-time only)
    // ------------------------------------------------------------------

    /**
     * PayU India has no usable stored-credential mandate in this
     * integration, so we cannot auto-charge a renewal. Throwing here
     * makes RenewDueSubscriptions fall into markRenewalFailed(): the
     * subscription enters its grace period and the buyer is emailed to
     * renew manually, instead of the renewal silently never happening.
     */
    public function chargeRecurring(\App\Modules\User\Models\Subscription $subscription): array
    {
        throw new NotImplementedException('PayU does not support automatic recurring charges; renewals are manual.');
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    protected function requestHash(string $key, string $salt, string $txnid, string $amount, string $productinfo, string $firstname, string $email): string
    {
        $parts = array_merge(
            [$key, $txnid, $amount, $productinfo, $firstname, $email],
            array_fill(0, 10, ''), // udf1..udf10
            [$salt],
        );
        return hash('sha512', implode('|', $parts));
    }

    protected function responseHash(string $salt, string $status, string $email, string $firstname, string $productinfo, string $amount, string $txnid, string $key, string $additionalCharges = ''): string
    {
        $parts = array_merge(
            [$salt, $status],
            array_fill(0, 10, ''), // udf10..udf1
            [$email, $firstname, $productinfo, $amount, $txnid, $key],
        );
        $base = implode('|', $parts);
        if ($additionalCharges !== '') {
            $base = $additionalCharges . '|' . $base;
        }
        return hash('sha512', $base);
    }

    /** Deterministic, alphanumeric, invoice-embedding PayU txn id. */
    protected function txnIdFor(Invoice $invoice): string
    {
        return 'inv' . $invoice->id . 'x' . substr(md5($invoice->number . '|' . $invoice->id), 0, 10);
    }

    protected function invoiceIdFromTxn(string $txnid): int
    {
        return preg_match('/^inv(\d+)x/', $txnid, $m) ? (int) $m[1] : 0;
    }

    protected function lookupPaymentId(Invoice $invoice): ?string
    {
        $rows = PaymentAttempt::where('gateway', 'payumoney')
            ->where('invoice_id', $invoice->id)
            ->orderByDesc('id')->limit(50)->get(['raw_response']);
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            $id  = $raw['mihpayid'] ?? null;
            if (is_string($id) && $id !== '') return $id;
        }
        return null;
    }

    protected function firstName(string $name): string
    {
        $name = trim($name);
        if ($name === '') return 'Customer';
        return explode(' ', $name)[0];
    }

    /** PayU requires a 10-digit phone; fall back to a neutral placeholder. */
    protected function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return strlen($digits) >= 10 ? substr($digits, -10) : '9999999999';
    }

    protected function minorToMoney(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    protected function moneyToMinor(string $money): int
    {
        return (int) round(((float) $money) * 100);
    }

    protected function assertOk(Response $res, string $op, ?Invoice $invoice): void
    {
        if ($res->successful()) return;
        $body = $res->json() ?: ['body' => $res->body()];
        Log::warning("PayU {$op} failed", ['status' => $res->status(), 'body' => $body]);

        if ($invoice) {
            try {
                PaymentAttempt::create([
                    'invoice_id'  => $invoice->id,
                    'gateway'     => 'payumoney',
                    'gateway_ref' => 'failed:' . $op . ':' . substr(md5(json_encode($body) . microtime()), 0, 16),
                    'status'      => 'failed',
                    'raw_response' => [
                        'op'     => $op,
                        'status' => $res->status(),
                        'body'   => $body,
                    ],
                ]);
            } catch (\Throwable $ignore) {
                // Never mask the user-facing error.
            }
        }

        $msg = $body['msg'] ?? $body['message'] ?? ('PayU API error (HTTP ' . $res->status() . ')');
        throw new \RuntimeException("PayU {$op} failed: {$msg}");
    }
}
