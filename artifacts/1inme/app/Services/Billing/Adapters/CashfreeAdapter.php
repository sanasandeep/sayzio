<?php

namespace App\Services\Billing\Adapters;

use App\Modules\User\Models\Invoice;
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
}
