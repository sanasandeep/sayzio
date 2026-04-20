<?php

namespace App\Events;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionActivated
{
    use Dispatchable;

    /**
     * @param array<int,array{label:string,amount_minor:int,quantity?:int}> $items
     */
    public function __construct(
        public User $user,
        public array $items,
        public string $currency,
        public ?string $gatewayRef = null,
    ) {}
}
