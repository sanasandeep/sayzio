<?php

namespace App\Listeners;

use App\Events\SubscriptionActivated;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\TaxCalculator;

class IssueInvoiceOnSubscriptionActivated
{
    public function handle(SubscriptionActivated $event): ?Invoice
    {
        $billing = BillingAddress::where('user_id', $event->user->id)->first();
        if (!$billing || empty($billing->country)) {
            return null;
        }

        $calc = TaxCalculator::calculate(
            $event->items,
            [
                'country'     => $billing->country,
                'region'      => $billing->region,
                'tax_id'      => $billing->tax_id,
                'tax_id_kind' => $billing->tax_id_kind,
            ],
            $event->currency,
        );

        return InvoiceService::issue($event->user, $calc, $billing->toArray());
    }
}
