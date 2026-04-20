<?php

namespace App\Services\Billing\Contracts;

use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\User\Models\Invoice;
use Illuminate\Http\Request;

/**
 * Every payment gateway — real or offline — implements this contract.
 *
 * createCheckout($invoice): hand off to the gateway. Returns a result
 *   array the caller can use to redirect the buyer:
 *   [
 *     'kind'   => 'redirect'|'view'|'handoff',
 *     'url'    => 'https://checkout.gateway/…'   // when kind=redirect
 *     'view'   => 'user.checkout.offline'        // when kind=view
 *     'data'   => [...]                          // data for the view
 *   ]
 *
 * verifyWebhook($request): return true iff the webhook signature is
 *   valid for THIS gateway's configured credentials. Stateless.
 *
 * parseEvent($request): translate the webhook payload into a canonical
 *   event array for the webhook router, WITHOUT touching the database:
 *   [
 *     'type'        => 'payment.succeeded'|'payment.failed'|'payment.requires_review',
 *     'invoice_id'  => int|null,   // internal id if derivable
 *     'gateway_ref' => string,     // gateway-side transaction id (idempotency key)
 *     'amount_minor'=> int|null,
 *     'currency'    => string|null,
 *     'raw'         => array,      // full payload snapshot
 *   ]
 */
interface GatewayAdapter
{
    public function slug(): string;
    public function displayName(): string;

    public function setSettings(?GatewaySetting $settings): void;

    public function createCheckout(Invoice $invoice): array;

    public function verifyWebhook(Request $request): bool;

    /** @return array<string,mixed> */
    public function parseEvent(Request $request): array;
}
