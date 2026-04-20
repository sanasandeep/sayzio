<?php

namespace App\Services\Billing\Adapters;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Services\Billing\NotImplementedException;
use Illuminate\Http\Request;

class CashfreeAdapter extends AbstractAdapter
{
    public function slug(): string { return 'cashfree'; }
    public function displayName(): string { return 'Cashfree'; }

    public function createCheckout(Invoice $invoice): array
    {
        throw new NotImplementedException('Cashfree integration ships in a later task.');
    }

    public function verifyWebhook(Request $request): bool
    {
        throw new NotImplementedException('Cashfree integration ships in a later task.');
    }

    public function parseEvent(Request $request): array
    {
        throw new NotImplementedException('Cashfree integration ships in a later task.');
    }

    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        throw new NotImplementedException('Cashfree refund ships in a later task.');
    }

    public function chargeRecurring(Subscription $subscription): array
    {
        throw new NotImplementedException('Cashfree recurring ships in a later task.');
    }
}
