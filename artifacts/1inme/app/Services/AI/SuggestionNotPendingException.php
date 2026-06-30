<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Task #3095 — thrown by {@see MarketingSuggestionApplier::claimAndApply()}
 * when a suggestion could not be atomically claimed because it was no longer
 * pending (already applied/dismissed/errored, or won by a concurrent request).
 * Carries the suggestion's current status so the caller can report it.
 */
class SuggestionNotPendingException extends RuntimeException
{
    public function __construct(protected ?string $currentStatus = null)
    {
        parent::__construct('This suggestion is no longer pending.');
    }

    public function currentStatus(): ?string
    {
        return $this->currentStatus;
    }
}
