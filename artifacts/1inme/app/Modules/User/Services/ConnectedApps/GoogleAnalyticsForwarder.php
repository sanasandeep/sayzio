<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google Analytics 4 server-side event forwarder (Measurement Protocol).
 *
 * A GA connection stores the Measurement ID in settings['measurement_id']
 * and the API secret in the encrypted `access_token` column (reusing the
 * encrypted cast so the secret is never stored in the clear). This is a
 * deliberate deviation from the OAuth CRM connectors — GA MP is keyed by a
 * measurement id + api secret, not OAuth.
 */
class GoogleAnalyticsForwarder
{
    private const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function measurementId(ConnectedApp $conn): ?string
    {
        $id = $conn->setting('measurement_id');
        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    public function apiSecret(ConnectedApp $conn): ?string
    {
        return $conn->access_token ?: null;
    }

    public function isConfigured(ConnectedApp $conn): bool
    {
        return $this->measurementId($conn) !== null && $this->apiSecret($conn) !== null;
    }

    /**
     * Forward a single event to GA4.
     *
     * @param array{name:string,client_id?:string,params?:array<string,mixed>} $event
     */
    public function forward(ConnectedApp $conn, array $event): bool
    {
        if (!$this->isConfigured($conn)) {
            return false;
        }
        $clientId = $event['client_id'] ?? ('sayzio.' . Str::random(16));
        $payload = [
            'client_id' => (string) $clientId,
            'events'    => [[
                'name'   => $event['name'] ?? 'sayzio_event',
                'params' => $event['params'] ?? [],
            ]],
        ];

        $resp = Http::withQueryParameters([
            'measurement_id' => $this->measurementId($conn),
            'api_secret'     => $this->apiSecret($conn),
        ])->post(self::ENDPOINT, $payload);

        // GA MP returns 204 No Content on success; it never returns validation
        // errors on the production endpoint, so treat any 2xx as accepted.
        if (!$resp->successful()) {
            throw new ConnectedAppException('GA4 forward failed: HTTP ' . $resp->status());
        }
        return true;
    }
}
