<?php

namespace App\Services\Brand;

/**
 * Thrown by AiBrandStudioService materializers when a per-type plan cap
 * blocks creating one proposed asset. Caught per-asset so the rest of the
 * kit still materializes; the message is surfaced on the results screen.
 */
class PlanCapReachedException extends \RuntimeException
{
}
