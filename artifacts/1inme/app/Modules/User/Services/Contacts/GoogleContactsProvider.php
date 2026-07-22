<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\GoogleContactsAccount;
use Illuminate\Support\Facades\Http;

class GoogleContactsProvider
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const PEOPLE_API = 'https://people.googleapis.com/v1';

    private const SCOPES = [
        'https://www.googleapis.com/auth/contacts',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    private const PERSON_FIELDS = 'names,emailAddresses,phoneNumbers,organizations,biographies,photos,metadata';

    public function clientId(): string
    {
        return (string) config('services.google_contacts.client_id', env('GOOGLE_CONTACTS_CLIENT_ID', ''));
    }

    public function clientSecret(): string
    {
        return (string) config('services.google_contacts.client_secret', env('GOOGLE_CONTACTS_CLIENT_SECRET', ''));
    }

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function authorizationUrl(string $stateToken, string $redirectUri): string
    {
        if (!$this->isConfigured()) {
            throw new ContactsSyncException('Google Contacts OAuth is not configured. Set GOOGLE_CONTACTS_CLIENT_ID and GOOGLE_CONTACTS_CLIENT_SECRET.');
        }
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'              => $this->clientId(),
            'redirect_uri'           => $redirectUri,
            'response_type'          => 'code',
            'scope'                  => implode(' ', self::SCOPES),
            'access_type'            => 'offline',
            'include_granted_scopes' => 'true',
            'prompt'                 => 'consent',
            'state'                  => $stateToken,
        ]);
    }

    public function exchangeCode(int $userId, string $code, string $redirectUri): GoogleContactsAccount
    {
        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $redirectUri,
        ]);
        if (!$resp->successful()) {
            if ($this->isRevokedGrantResponse($resp->json())) {
                throw new GoogleReauthRequiredException('Google rejected the authorization — please try connecting again.');
            }
            throw new ContactsSyncException('Google token exchange failed: ' . $resp->body());
        }
        $tok = $resp->json();

        $email = null; $extId = null;
        $info = Http::withToken($tok['access_token'])->get('https://www.googleapis.com/oauth2/v3/userinfo');
        if ($info->successful()) {
            $email = $info->json('email');
            $extId = $info->json('sub');
        }

        return GoogleContactsAccount::updateOrCreate(
            ['user_id' => $userId, 'account_email' => $email],
            [
                'external_account_id' => $extId,
                'access_token'        => $tok['access_token'] ?? null,
                'refresh_token'       => $tok['refresh_token'] ?? null,
                'token_expires_at'    => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
                'scope'               => $tok['scope'] ?? implode(' ', self::SCOPES),
                'sync_token'          => null, // force initial full pull
                'last_sync_status'    => null,
                'last_sync_error'     => null,
                'needs_reauth_at'     => null, // reconnect clears the expired state
                'reauth_reminder_sent_at' => null, // re-arm the 7-day follow-up reminder
            ]
        )->fresh();
    }

    public function refreshIfNeeded(GoogleContactsAccount $account): void
    {
        if ($account->needsReauth()) {
            throw new GoogleReauthRequiredException('Google Contacts connection expired — please reconnect.');
        }
        if (!$account->token_expires_at || $account->token_expires_at->isFuture()) return;
        if (!$account->refresh_token) {
            $account->markNeedsReauth('Google account has no refresh token.');
            throw new GoogleReauthRequiredException('Google account has no refresh token — please reconnect.');
        }
        $resp = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $account->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);
        if (!$resp->successful()) {
            // A revoked/expired refresh token comes back as invalid_grant (or
            // invalid_client for deleted OAuth clients). Persist the state so
            // sync paths stop retrying until the user reconnects.
            if ($this->isRevokedGrantResponse($resp->json())) {
                $account->markNeedsReauth('Google reported the connection as revoked or expired (' . ($resp->json('error') ?? 'invalid_grant') . ').');
                throw new GoogleReauthRequiredException('Google Contacts connection expired — please reconnect.');
            }
            throw new ContactsSyncException('Google refresh failed: ' . $resp->body());
        }
        $tok = $resp->json();
        $account->update([
            'access_token'     => $tok['access_token'] ?? $account->access_token,
            'token_expires_at' => isset($tok['expires_in']) ? now()->addSeconds((int) $tok['expires_in']) : null,
        ]);
    }

    /**
     * Yield people connections, paginated. If the account has a stored sync_token,
     * an incremental pull is used. The caller receives the new sync_token via
     * the second arg of the callback.
     *
     * @return iterable<array> normalized people payloads
     */
    public function listPeople(GoogleContactsAccount $account, ?callable $onNewSyncToken = null): iterable
    {
        $this->refreshIfNeeded($account);
        $pageToken = null;
        $useSyncToken = (bool) $account->sync_token;
        do {
            $params = array_filter([
                'personFields'  => self::PERSON_FIELDS,
                'pageSize'      => 200,
                'pageToken'     => $pageToken,
                'requestSyncToken' => 'true',
                'syncToken'     => $useSyncToken ? $account->sync_token : null,
            ], fn($v) => $v !== null);
            $resp = Http::withToken($account->access_token)
                ->get(self::PEOPLE_API . '/people/me/connections', $params);
            if ($resp->status() === 410) {
                // sync token expired — clear and fall back to full pull on next call
                $account->update(['sync_token' => null]);
                throw new ContactsSyncException('Google sync token expired — retrying full pull.');
            }
            if (!$resp->successful()) {
                throw new ContactsSyncException('Google list failed: ' . $resp->body());
            }
            $data = $resp->json();
            foreach (($data['connections'] ?? []) as $person) {
                $payload = $this->normalize($person);
                if ($payload) yield $payload;
            }
            $pageToken = $data['nextPageToken'] ?? null;
            if (!$pageToken && !empty($data['nextSyncToken']) && $onNewSyncToken) {
                $onNewSyncToken($data['nextSyncToken']);
            }
        } while ($pageToken);
    }

    public function createPerson(GoogleContactsAccount $account, array $payload): array
    {
        $this->refreshIfNeeded($account);
        $body = $this->denormalize($payload);
        $resp = Http::withToken($account->access_token)
            ->withQueryParameters(['personFields' => self::PERSON_FIELDS])
            ->post(self::PEOPLE_API . '/people:createContact', $body);
        if (!$resp->successful()) {
            throw new ContactsSyncException('Google create failed: ' . $resp->body());
        }
        return $this->normalize($resp->json()) ?? [];
    }

    public function updatePerson(GoogleContactsAccount $account, string $resourceName, string $etag, array $payload): array
    {
        $this->refreshIfNeeded($account);
        $body = $this->denormalize($payload);
        $body['etag'] = $etag;
        $resp = Http::withToken($account->access_token)
            ->withQueryParameters([
                'updatePersonFields' => 'names,emailAddresses,phoneNumbers,organizations,biographies',
                'personFields'       => self::PERSON_FIELDS,
            ])
            ->patch(self::PEOPLE_API . '/' . ltrim($resourceName, '/') . ':updateContact', $body);
        if (!$resp->successful()) {
            throw new ContactsSyncException('Google update failed: ' . $resp->body());
        }
        return $this->normalize($resp->json()) ?? [];
    }

    public function deletePerson(GoogleContactsAccount $account, string $resourceName): void
    {
        $this->refreshIfNeeded($account);
        $resp = Http::withToken($account->access_token)
            ->delete(self::PEOPLE_API . '/' . ltrim($resourceName, '/') . ':deleteContact');
        if (!$resp->successful() && $resp->status() !== 404) {
            throw new ContactsSyncException('Google delete failed: ' . $resp->body());
        }
    }

    /** True when a token-endpoint error body means the grant is revoked/expired. */
    private function isRevokedGrantResponse(?array $body): bool
    {
        return in_array($body['error'] ?? null, ['invalid_grant', 'invalid_client'], true);
    }

    private function normalize(array $person): ?array
    {
        $name        = $person['names'][0] ?? [];
        $org         = $person['organizations'][0] ?? [];
        $deleted     = collect($person['metadata']['sources'] ?? [])
                        ->contains(fn($s) => !empty($s['etag']) && ($person['metadata']['deleted'] ?? false));

        $phones = [];
        foreach (($person['phoneNumbers'] ?? []) as $p) {
            if (empty($p['value'])) continue;
            $phones[] = [
                'label'   => $p['formattedType'] ?? ($p['type'] ?? null),
                'value'   => $p['value'],
                'primary' => (bool) ($p['metadata']['primary'] ?? false),
            ];
        }
        $emails = [];
        foreach (($person['emailAddresses'] ?? []) as $e) {
            if (empty($e['value'])) continue;
            $emails[] = [
                'label'   => $e['formattedType'] ?? ($e['type'] ?? null),
                'value'   => $e['value'],
                'primary' => (bool) ($e['metadata']['primary'] ?? false),
            ];
        }

        return [
            'resource_name'  => $person['resourceName'] ?? null,
            'etag'           => $person['etag'] ?? null,
            'deleted'        => (bool) ($person['metadata']['deleted'] ?? false),
            'display_name'   => $name['displayName'] ?? null,
            'given_name'     => $name['givenName']   ?? null,
            'family_name'    => $name['familyName']  ?? null,
            'organization'   => $org['name']         ?? null,
            'job_title'      => $org['title']        ?? null,
            'notes'          => $person['biographies'][0]['value'] ?? null,
            'photo_url'      => $person['photos'][0]['url'] ?? null,
            'phones'         => $phones,
            'emails'         => $emails,
        ];
    }

    private function denormalize(array $payload): array
    {
        $body = [];
        if (!empty($payload['given_name']) || !empty($payload['family_name']) || !empty($payload['display_name'])) {
            $body['names'] = [[
                'givenName'  => $payload['given_name']  ?? null,
                'familyName' => $payload['family_name'] ?? null,
            ]];
        }
        if (!empty($payload['organization']) || !empty($payload['job_title'])) {
            $body['organizations'] = [[
                'name'  => $payload['organization'] ?? null,
                'title' => $payload['job_title']    ?? null,
            ]];
        }
        if (!empty($payload['notes'])) {
            $body['biographies'] = [['value' => $payload['notes'], 'contentType' => 'TEXT_PLAIN']];
        }
        $body['phoneNumbers'] = [];
        foreach ($payload['phones'] ?? [] as $p) {
            if (empty($p['value'])) continue;
            $body['phoneNumbers'][] = ['value' => $p['value'], 'type' => $p['label'] ?? 'mobile'];
        }
        $body['emailAddresses'] = [];
        foreach ($payload['emails'] ?? [] as $e) {
            if (empty($e['value'])) continue;
            $body['emailAddresses'][] = ['value' => $e['value'], 'type' => $e['label'] ?? 'home'];
        }
        return $body;
    }
}

class ContactsSyncException extends \RuntimeException {}

/**
 * Thrown when Google reports the OAuth grant as revoked or expired
 * (invalid_grant / invalid_client). Callers should stop retrying and prompt
 * the user to reconnect instead of surfacing the raw token-endpoint error.
 */
class GoogleReauthRequiredException extends ContactsSyncException {}
