<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when an AI feature tries to charge more credits than the user
 * has available. Feature UIs catch this to surface a "top up" prompt.
 */
class InsufficientAiCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $required,
        public readonly int $balance,
    ) {
        parent::__construct(
            "Insufficient AI credits: need {$required}, have {$balance}.",
            402
        );
    }
}
