<?php

namespace App\Modules\User\Exceptions;

use RuntimeException;

/**
 * Thrown by UserFile upload paths when the user's per-plan storage quota
 * would be exceeded. A dedicated subclass (rather than a bare
 * RuntimeException) lets API controllers translate the failure into the
 * standard plan-gate hint envelope (402 + `recommended_plan` in
 * error.details) instead of a generic 422 `upload_failed`, so clients can
 * route the creator to the upgrade screen.
 */
class StorageQuotaExceededException extends RuntimeException
{
    /** Plan feature key the quota derives from (see User::getStorageLimitBytes). */
    public const FEATURE = 'storage_limit_mb';
}
