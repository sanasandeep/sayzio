<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Support\Facades\Http;

/**
 * Shared OAuth 2.0 authorization-code plumbing for the CRM connectors. Reads
 * platform client credentials from PlatformServiceSettings (admin-provided,
 * with env fallback) and the endpoints/scopes from the data-driven registry,
 * so a new OAuth CRM only needs a subclass implementing the read/write API
 * calls (pushContact / pullContacts) plus optional afterExchange().
 */
abstract class AbstractOAuthConnector implements ConnectorContract
{
    abstract protected function providerKey(): string;

    protected function meta(): array
    {
        return ConnectedAppRegistry::provider($this->providerKey()) ?? [];
    }

    protected function oauth(): array
    {
        return $this->meta()['oauth'] ?? [];
    }

    protected function clientId(): ?string
    {
        return PlatformServiceSettings::connectedAppClientId($this->providerKey());
    }

    protected function clientSecret(): ?string
    {
        return PlatformServiceSettings::connectedAppClientSecret($this->providerKey());
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== null && $this->clientSecret() !== null;
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        if (!$this->isConfigured()) {
            throw new ConnectedAppException($this->meta()['label'] . ' is not configured yet.');
        }
        $o = $this->oauth();
        return $o['auth_url'] . '?' . http_build_query(array_merge([
            'response_type'         => 'code',
            'client_id'             => $this->clientId(),
            'redirect_uri'          => $redirectUri,
            'scope'                 => implode(' ', $o['scopes'] ?? []),
            'state'                 => $state,
            'access_type'           => 'offline',
            'prompt'                => 'consent',
        ], $this->extraAuthParams()));
    }

    /** Provider-specific extra authorize query params. */
    protected function extraAuthParams(): array
    {
        return [];
    }

    public function exchangeCode(ConnectedApp $conn, string $code, string $redirectUri): void
    {
        $o = $this->oauth();
        $resp = Http::asForm()->post($o['token_url'], [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri'  => $redirectUri,
        ]);
        if (!$resp->successful()) {
            throw new ConnectedAppException($this->meta()['label'] . ' token exchange failed: ' . $resp->body());
        }
        $tok = $resp->json();

        $conn->access_token = $tok['access_token'] ?? null;
        if (!empty($tok['refresh_token'])) {
            $conn->refresh_token = $tok['refresh_token'];
        }
        $conn->token_expires_at = isset($tok['expires_in'])
            ? now()->addSeconds((int) $tok['expires_in'])
            : null;
        $conn->scope = $tok['scope'] ?? implode(' ', $o['scopes'] ?? []);
        if (!empty($tok['instance_url'])) {
            $conn->instance_url = rtrim($tok['instance_url'], '/');
        }
        if (!empty($tok['api_domain'])) {
            $conn->instance_url = rtrim($tok['api_domain'], '/');
        }
        $conn->status       = ConnectedApp::STATUS_CONNECTED;
        $conn->connected_at = now();

        $this->afterExchange($conn, $tok);
        $conn->save();
    }

    /** Hook for provider identity lookup (account label/email/external id). */
    protected function afterExchange(ConnectedApp $conn, array $tok): void
    {
    }

    public function refreshIfNeeded(ConnectedApp $conn): void
    {
        if (!$conn->token_expires_at || $conn->token_expires_at->isFuture()) {
            return;
        }
        if (!$conn->refresh_token) {
            throw new ConnectedAppException($this->meta()['label'] . ' has no refresh token — please reconnect.');
        }
        $o = $this->oauth();
        $resp = Http::asForm()->post($o['token_url'], [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $conn->refresh_token,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);
        if (!$resp->successful()) {
            throw new ConnectedAppException($this->meta()['label'] . ' token refresh failed: ' . $resp->body());
        }
        $tok = $resp->json();
        $conn->access_token = $tok['access_token'] ?? $conn->access_token;
        $conn->token_expires_at = isset($tok['expires_in'])
            ? now()->addSeconds((int) $tok['expires_in'])
            : null;
        if (!empty($tok['instance_url'])) {
            $conn->instance_url = rtrim($tok['instance_url'], '/');
        }
        $conn->save();
    }

    /**
     * Map a normalized Sayzio "lead" onto provider field names using the
     * connection's field mappings (falling back to registry defaults).
     *
     * @return array<string,mixed>
     */
    protected function mapLead(ConnectedApp $conn, array $lead): array
    {
        $mappings = $conn->field_mappings ?: ($this->meta()['default_field_mappings'] ?? []);
        $out = [];
        foreach ($mappings as $sayzioField => $providerField) {
            $val = $lead[$sayzioField] ?? null;
            if ($val !== null && $val !== '' && $providerField) {
                $out[$providerField] = $val;
            }
        }
        return $out;
    }

    /** A bearer-authorized HTTP client for the connection's access token. */
    protected function client(ConnectedApp $conn)
    {
        $this->refreshIfNeeded($conn);
        return Http::withToken($conn->access_token)->acceptJson();
    }
}
