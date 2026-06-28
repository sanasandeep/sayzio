<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Thrown when AI Artistic QR is requested but no Replicate API token is
 * configured (neither an admin-stored key nor the env fallback). Feature
 * UIs treat this as "preview / disabled" mode rather than an error.
 */
class QrArtUnavailableException extends RuntimeException
{
}
