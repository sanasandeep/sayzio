<?php

namespace App\Listeners;

use App\Events\SubscriptionActivated;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\TaxCalculator;

class IssueInvoiceOnSubscriptionActivated
{
    public function handle(SubscriptionActivated $event): Invoice
    {
        $billing = BillingAddress::where('user_id', $event->user->id)->first();

        $address = [
            'country'       => $billing?->country ?? ($event->user->country ?? null),
            'region'        => $billing?->region,
            'postal_code'   => $billing?->postal_code,
            'line1'         => $billing?->line1,
            'line2'         => $billing?->line2,
            'city'          => $billing?->city,
            'tax_id'        => $billing?->tax_id,
            'tax_id_kind'   => $billing?->tax_id_kind,
            'business_name' => $billing?->business_name,
            'buyer_name'    => $event->user->name,
        ];

        $calc = TaxCalculator::calculate(
            $event->items,
            [
                'country'     => $address['country'],
                'region'      => $address['region'],
                'tax_id'      => $address['tax_id'],
                'tax_id_kind' => $address['tax_id_kind'],
            ],
            $event->currency,
        );

        return InvoiceService::issue($event->user, $calc, $address);
    }
}
