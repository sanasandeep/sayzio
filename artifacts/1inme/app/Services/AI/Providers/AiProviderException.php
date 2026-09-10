<?php

namespace App\Services\AI\Providers;

/**
 * A provider answered with a non-2xx.
 *
 * Carries the HTTP status because the fallback chain has to tell two very
 * different situations apart:
 *
 *   - 429 / 5xx / a connection failure: the vendor is rate-limiting or down.
 *     Another vendor would very likely succeed, so falling back is right.
 *
 *   - 400 / 404 / 422: WE sent something wrong -- an unknown model name, a
 *     malformed tool schema, an oversized prompt. Every other vendor will
 *     reject it too, and retrying around the ring just multiplies the
 *     latency and the log noise before failing anyway.
 *
 *   - 401 / 403: the key is missing, revoked or out of quota. Worth trying
 *     the next provider (its key is a different key), but it is also
 *     something the admin has to hear about, so it is reported separately.
 */
class AiProviderException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $provider = null,
    ) {
        parent::__construct($message);
    }

    /** Would another vendor plausibly succeed where this one failed? */
    public function isWorthFallingBackFrom(): bool
    {
        // 0 covers connection failures, which never reached the vendor.
        return $this->status === 0
            || $this->status === 401
            || $this->status === 403
            || $this->status === 408
            || $this->status === 409
            || $this->status === 429
            || $this->status >= 500;
    }

    /** Does the admin need to know a key is bad, rather than a vendor being busy? */
    public function isCredentialProblem(): bool
    {
        return $this->status === 401 || $this->status === 403;
    }
}
