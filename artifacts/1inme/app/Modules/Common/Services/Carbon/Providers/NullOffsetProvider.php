<?php

namespace App\Modules\Common\Services\Carbon\Providers;

use App\Modules\Common\Services\Carbon\Contracts\OffsetProvider;
use Illuminate\Http\Request;

/**
 * Sandbox/no-op provider used when no IntegrationConfig of kind=carbon
 * is configured. Records grams + cost as if a purchase succeeded so
 * creators can see the full UX (badge, dashboard, certificates page)
 * before connecting a real account. Cost is computed at the
 * sandbox-default $14/tonne CO2 rate — within the public market range
 * for verified renewables-backed offsets at time of writing.
 */
class NullOffsetProvider implements OffsetProvider
{
    public const SANDBOX_USD_PER_TONNE = 14;

    public function slug(): string { return 'sandbox'; }

    public function purchase(int $workspaceId, float $grams, string $currency, string $idempotencyKey): array
    {
        // grams → tonnes × USD/tonne × 100 (cents)
        $tonnes    = max(0.0, $grams) / 1_000_000.0;
        $costCents = (int) max(1, round($tonnes * self::SANDBOX_USD_PER_TONNE * 100));

        return [
            'provider_ref'    => 'sandbox_' . substr($idempotencyKey, 0, 24),
            'status'          => 'sandbox',
            'cost_minor'      => $costCents,
            'currency'        => 'USD',
            'certificate_url' => null,
            'project_name'    => 'Sandbox: Verified renewables portfolio',
            'raw'             => [
                'sandbox' => true,
                'note'    => 'No real offset purchased. Connect a Cloverly or Patch account from Integrations to enable live purchases.',
                'grams'   => $grams,
                'tonnes'  => $tonnes,
                'rate'    => ['unit' => 'USD/tCO2', 'value' => self::SANDBOX_USD_PER_TONNE],
            ],
        ];
    }

    public function verifyWebhook(Request $request): bool { return false; }

    public function parseWebhook(Request $request): array
    {
        return ['provider_ref' => '', 'status' => 'failed', 'certificate_url' => null, 'project_name' => null, 'raw' => []];
    }
}
