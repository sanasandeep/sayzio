<?php

namespace App\Modules\User\Services;

/**
 * Raised when an organizer→guest broadcast is refused by the per-link
 * cooldown or the rolling 24h daily cap. The message is user-facing and
 * surfaced verbatim on both the web and API paths.
 */
class EventBroadcastLimitException extends \RuntimeException
{
}
