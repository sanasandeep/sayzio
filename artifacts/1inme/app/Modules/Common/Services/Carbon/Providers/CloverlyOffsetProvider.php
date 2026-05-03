<?php

namespace App\Modules\Common\Services\Carbon\Providers;

use App\Modules\Common\Services\Carbon\Contracts\OffsetProvider;
use App\Modules\User\Models\IntegrationConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloverly offset provider adapter.
 *
 * Reads its `api_key` (and optional `webhook_secret`) from the
 * workspace's IntegrationConfig of kind=carbon, provider=cloverly.
 *
 * Sandbox-first: if `mode` is unset or "sandbox", we hit Cloverly's
 * sandbox base URL and tag the resulting purchase row with
 * `status=sandbox` so the dashboard can show a "Sandbox" pill.
 *
 * The full Cloverly API surface is large; we only call:
 *   POST /2019-03-beta/purchases/carbon
 *
 * with a `weight: { value, units: 'g' }` payload. The response carries
 * a `pretty_url` (cert) and `offset.name` we surface in the dashboard.
 */
class CloverlyOffsetProvider implements OffsetProvider
{
    public const BASE_LIVE    = 'https://api.cloverly.app';
    public const BASE_SANDBOX = 'https://api.cloverly.app'; // sandbox keys route via api_key prefix

    public function slug(): string { return 'cloverly'; }

    public function quote(int $workspaceId, float $grams, string $currency): array
    {
        $cfg = $this->config($workspaceId);
        if (!$cfg) {
            return (new NullOffsetProvider())->quote($workspaceId, $grams, $currency);
        }

        $apiKey = (string) ($cfg->credentials['api_key'] ?? '');
        $base   = self::BASE_LIVE; // estimates endpoint is the same for sandbox keys

        try {
            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->timeout(15)
                ->post($base . '/2019-03-beta/estimates/carbon', [
                    'weight' => ['value' => max(1, (int) round($grams)), 'units' => 'g'],
                ]);

            if (!$resp->successful()) {
                Log::warning('cloverly_quote_failed', [
                    'workspace_id' => $workspaceId, 'status' => $resp->status(),
                ]);
                return (new NullOffsetProvider())->quote($workspaceId, $grams, $currency);
            }

            $body  = $resp->json();
            $cents = (int) round((float) ($body['total_cost']['cents'] ?? 0));
            if ($cents <= 0) {
                $cents = (int) round(((float) ($body['total_cost']['amount'] ?? 0)) * 100);
            }

            return [
                'cost_minor'           => $cents > 0 ? $cents : 1,
                'currency'             => strtoupper((string) ($body['total_cost']['currency'] ?? 'USD')),
                'rate_per_tonne_minor' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('cloverly_quote_exception', ['error' => $e->getMessage()]);
            return (new NullOffsetProvider())->quote($workspaceId, $grams, $currency);
        }
    }

    public function purchase(int $workspaceId, float $grams, string $currency, string $idempotencyKey): array
    {
        $cfg = $this->config($workspaceId);
        if (!$cfg) {
            // No live creds → defer to NullOffsetProvider so callers
            // never see an unconfigured-error path.
            return (new NullOffsetProvider())->purchase($workspaceId, $grams, $currency, $idempotencyKey);
        }

        $apiKey = (string) ($cfg->credentials['api_key'] ?? '');
        $mode   = strtolower((string) ($cfg->meta['mode'] ?? 'sandbox'));
        $base   = $mode === 'live' ? self::BASE_LIVE : self::BASE_SANDBOX;

        try {
            $resp = Http::withHeaders([
                    'Authorization'    => 'Bearer ' . $apiKey,
                    'Content-Type'     => 'application/json',
                    'Idempotency-Key'  => $idempotencyKey,
                ])
                ->timeout(20)
                ->post($base . '/2019-03-beta/purchases/carbon', [
                    'weight' => ['value' => max(1, (int) round($grams)), 'units' => 'g'],
                ]);

            if (!$resp->successful()) {
                Log::warning('cloverly_purchase_failed', [
                    'workspace_id' => $workspaceId, 'status' => $resp->status(), 'body' => $resp->body(),
                ]);
                return $this->failed($currency, $resp->json());
            }

            $body  = $resp->json();
            $cents = (int) round(((float) ($body['total_cost']['cents'] ?? 0)));
            if ($cents <= 0) {
                $cents = (int) round(((float) ($body['total_cost']['amount'] ?? 0)) * 100);
            }

            // Map Cloverly's returned state to our internal vocabulary.
            // For LIVE purchases we default to 'pending' until the
            // settlement webhook fires — Cloverly only marks the
            // purchase final once the offset registry has retired the
            // credits, which can take minutes to hours. The webhook
            // handler in CarbonPublicController promotes pending →
            // purchased on confirmation, and the public badge
            // endpoint hides any snapshot whose status is still
            // pending so we never claim "carbon neutral" prematurely.
            $providerState = strtolower((string) ($body['state'] ?? $body['status'] ?? ''));
            if ($mode !== 'live') {
                $internalStatus = 'sandbox';
            } else {
                $internalStatus = match ($providerState) {
                    'complete', 'completed', 'succeeded', 'success' => 'succeeded',
                    'failed', 'error', 'cancelled', 'canceled'      => 'failed',
                    default                                          => 'pending',
                };
            }

            return [
                'provider_ref'    => (string) ($body['slug'] ?? ($body['id'] ?? $idempotencyKey)),
                'status'          => $internalStatus,
                'cost_minor'      => $cents > 0 ? $cents : 1,
                'currency'        => strtoupper((string) ($body['total_cost']['currency'] ?? 'USD')),
                'certificate_url' => $body['pretty_url'] ?? null,
                'project_name'    => $body['offset']['name'] ?? ($body['offset']['type'] ?? 'Verified offset portfolio'),
                'raw'             => $body,
            ];
        } catch (\Throwable $e) {
            Log::warning('cloverly_purchase_exception', ['workspace_id' => $workspaceId, 'error' => $e->getMessage()]);
            return $this->failed($currency, ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify a Cloverly webhook. The payload itself never carries
     * a trustworthy workspace identifier, so we look up the workspace
     * by the immutable `purchase.slug` (recorded in our DB at
     * purchase time as `provider_ref`), fetch THAT workspace's
     * configured webhook secret, and HMAC-verify the request body
     * against it. Falls back to `false` (401) when:
     *   - the payload doesn't carry a slug we recognise, or
     *   - the matched workspace has no webhook secret configured, or
     *   - the signature doesn't match.
     */
    public function verifyWebhook(Request $request): bool
    {
        $body     = $request->json()->all();
        $purchase = $body['purchase'] ?? $body;
        $ref      = (string) ($purchase['slug'] ?? $purchase['id'] ?? '');
        if ($ref === '') return false;

        $row = \App\Modules\User\Models\CarbonOffsetPurchase::query()
            ->withoutGlobalScope('workspace')
            ->where('provider', $this->slug())
            ->where('provider_ref', $ref)
            ->first();
        if (!$row) return false;

        $cfg    = $this->config((int) $row->workspace_id);
        $secret = (string) ($cfg?->credentials['webhook_secret'] ?? '');
        if ($secret === '') return false;

        $sig = (string) $request->header('X-Cloverly-Signature', '');
        return $sig !== '' && hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            $sig
        );
    }

    public function parseWebhook(Request $request): array
    {
        $body = $request->json()->all();
        $purchase = $body['purchase'] ?? $body;
        $statusRaw = strtolower((string) ($purchase['state'] ?? $purchase['status'] ?? 'succeeded'));
        return [
            'provider_ref'    => (string) ($purchase['slug'] ?? $purchase['id'] ?? ''),
            'status'          => in_array($statusRaw, ['succeeded', 'completed', 'paid'], true) ? 'succeeded' : 'failed',
            'certificate_url' => $purchase['pretty_url'] ?? null,
            'project_name'    => $purchase['offset']['name'] ?? null,
            'raw'             => $body,
        ];
    }

    private function config(int $workspaceId): ?IntegrationConfig
    {
        return IntegrationConfig::query()->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where('kind', 'carbon')
            ->where('provider', 'cloverly')
            ->where('is_active', true)
            ->first();
    }

    private function failed(string $currency, $raw): array
    {
        return [
            'provider_ref'    => null,
            'status'          => 'failed',
            'cost_minor'      => 0,
            'currency'        => $currency ?: 'USD',
            'certificate_url' => null,
            'project_name'    => null,
            'raw'             => is_array($raw) ? $raw : ['raw' => $raw],
        ];
    }
}
