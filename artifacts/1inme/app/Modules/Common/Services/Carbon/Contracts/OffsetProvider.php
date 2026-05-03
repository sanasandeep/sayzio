<?php

namespace App\Modules\Common\Services\Carbon\Contracts;

use Illuminate\Http\Request;

/**
 * Thin abstraction for any third-party carbon-offset purchase API
 * (Cloverly, Patch, etc.). Keeps the monthly job vendor-neutral and
 * makes a sandbox/null fallback drop-in.
 *
 * `purchase()` MUST be idempotent on (workspace_id, idempotencyKey).
 * Returns a normalised payload:
 *
 *   [
 *     'provider_ref'    => string|null,
 *     'status'          => 'succeeded'|'pending'|'failed'|'sandbox',
 *     'cost_minor'      => int,           // total cost in `currency` minor units
 *     'currency'        => string,        // ISO 4217
 *     'certificate_url' => string|null,
 *     'project_name'    => string|null,
 *     'raw'             => array,         // full provider response (or sandbox stub)
 *   ]
 */
interface OffsetProvider
{
    public function slug(): string;

    public function purchase(int $workspaceId, float $grams, string $currency, string $idempotencyKey): array;

    /**
     * Verify a webhook from the provider (signature/HMAC). Sandbox
     * implementations should return false unless explicitly enabled.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Translate a verified webhook payload into a normalised shape:
     *   [
     *     'provider_ref'    => string,
     *     'status'          => 'succeeded'|'failed',
     *     'certificate_url' => string|null,
     *     'project_name'    => string|null,
     *     'raw'             => array,
     *   ]
     */
    public function parseWebhook(Request $request): array;
}
