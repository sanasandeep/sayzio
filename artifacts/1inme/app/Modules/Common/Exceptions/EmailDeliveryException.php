<?php

namespace App\Modules\Common\Exceptions;

use App\Modules\Common\Models\EmailLog;
use RuntimeException;
use Throwable;

/**
 * Raised by Emailer when a send is requested with the opt-in
 * `throw_on_failure` flag and the underlying mail transport fails.
 *
 * The central Emailer pipeline otherwise swallows transport failures (it
 * logs a `failed` email_logs row and returns normally). Callers that must
 * NOT proceed on a silent delivery failure — e.g. stamping a client invoice
 * "sent" only after it was actually delivered — opt in to this exception so
 * the failure surfaces instead of being hidden.
 *
 * Carries the `failed` EmailLog row (when one was written) so callers can
 * inspect/relate it.
 */
class EmailDeliveryException extends RuntimeException
{
    public function __construct(
        string $message = 'Email delivery failed.',
        public readonly ?EmailLog $log = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
