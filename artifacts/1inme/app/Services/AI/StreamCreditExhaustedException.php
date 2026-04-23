<?php

namespace App\Services\AI;

/**
 * Thrown by {@see OpenAiService::chatStream()} when a streamed reply
 * pushed the user past zero credits mid-stream and the upstream call
 * was stopped early. Carries the partial content the visitor already
 * saw plus the token usage we ended up charging for, so callers can
 * persist a partial transcript entry and surface a clear "your reply
 * was cut short" notice instead of letting the stream go silent.
 */
class StreamCreditExhaustedException extends InsufficientAiCreditsException
{
    public function __construct(
        int $required,
        int $balance,
        public readonly string $partialContent,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly int $creditsSpent,
    ) {
        parent::__construct($required, $balance);
    }
}
