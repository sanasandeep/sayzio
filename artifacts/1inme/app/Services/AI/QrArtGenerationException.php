<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when the Replicate QR-ControlNet call fails, times out, or
 * returns no usable image. The charged coins are auto-refunded by
 * {@see QrArtService} before this propagates.
 */
class QrArtGenerationException extends RuntimeException
{
}
