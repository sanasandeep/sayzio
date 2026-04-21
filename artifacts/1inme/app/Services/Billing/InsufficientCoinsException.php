<?php

namespace App\Services\Billing;

use RuntimeException;

/**
 * Thrown by WalletService::debit when the user doesn't have enough
 * coins. Carries `required` and `balance` so callers can render an
 * accurate "Top up coins" prompt.
 */
class InsufficientCoinsException extends RuntimeException
{
    public function __construct(public int $required, public int $balance)
    {
        parent::__construct("Insufficient coins: need {$required}, have {$balance}.");
    }
}
