<?php

namespace App\Services\AI;

/**
 * Thrown when a user has exhausted their plan's monthly AI Artistic QR
 * generation allowance (`max_qr_art_monthly`). Raised BEFORE any coin
 * charge so an over-allowance attempt never costs anything.
 */
class QrArtAllowanceExceededException extends \RuntimeException
{
    public function __construct(
        string $message,
        /** Normalized plan allowance (finite, never the bypass sentinel). */
        public readonly int $allowance,
        /** Successful generations already counted this month. */
        public readonly int $used,
    ) {
        parent::__construct($message);
    }
}
