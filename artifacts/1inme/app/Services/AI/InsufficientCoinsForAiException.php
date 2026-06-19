<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when an AI feature tries to charge more coins than the user has
 * in their wallet. AI usage is billed straight from the coin wallet, so
 * this is the AI-flavoured counterpart of the wallet's
 * {@see \App\Services\Billing\InsufficientCoinsException}. Feature UIs
 * catch this to surface a "top up" prompt.
 */
class InsufficientCoinsForAiException extends RuntimeException
{
    public function __construct(
        public readonly int $required,
        public readonly int $balance,
    ) {
        parent::__construct(
            "Insufficient coins for AI: need {$required}, have {$balance}.",
            402
        );
    }
}
