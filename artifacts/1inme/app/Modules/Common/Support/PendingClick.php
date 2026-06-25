<?php

namespace App\Modules\Common\Support;

use App\Modules\Common\Services\ClickWriteBuffer;

/**
 * Lightweight handle returned by LinkTrackingService instead of a persisted
 * LinkClick model. The actual row is written later by PersistLinkClicksJob, so
 * there is no database id yet — callers that need to enrich the click (e.g.
 * RedirectController stamping the matched smart-rule id) mutate the still-buffered
 * payload through this handle before it is flushed to the queue at request end.
 *
 * A non-null PendingClick from trackBlockClick() preserves the old contract that
 * "non-null == the click was accepted (cap reservation won / not throttled)".
 */
class PendingClick
{
    public function __construct(
        private ClickWriteBuffer $buffer,
        private int $index,
        public readonly string $eventId,
        public readonly bool $isBot,
    ) {
    }

    /**
     * Stamp the matched smart-routing rule id onto the buffered click payload.
     * No-op for empty ids. Safe to call any time before the request terminates
     * (when the buffer flushes to the queue).
     */
    public function setMatchedRuleId(?string $ruleId): void
    {
        if ($ruleId === null || $ruleId === '') {
            return;
        }
        $this->buffer->setField($this->index, 'matched_rule_id', (string) $ruleId);
    }
}
