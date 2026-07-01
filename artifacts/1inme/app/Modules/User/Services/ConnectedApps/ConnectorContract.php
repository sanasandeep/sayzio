<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;

/**
 * Contract every CRM connector implements. GA4 is an analytics forwarder and
 * intentionally does NOT implement this — see GoogleAnalyticsForwarder.
 *
 * Normalized shapes shared across connectors:
 *  - push "lead" input:  ['email','first_name','last_name','display_name','phone','company','source']
 *  - pulled contact:     ['external_id','display_name','given_name','family_name','organization',
 *                         'job_title','notes','photo_url','phones'=>[{label,value,primary}],
 *                         'emails'=>[{label,value,primary}]]
 */
interface ConnectorContract
{
    /** Whether the platform-level OAuth client credentials are present. */
    public function isConfigured(): bool;

    /** Build the provider OAuth consent URL for a creator to authorize. */
    public function authorizationUrl(string $state, string $redirectUri): string;

    /** Exchange an auth code and persist tokens onto the connection. */
    public function exchangeCode(ConnectedApp $conn, string $code, string $redirectUri): void;

    /** Refresh the access token if it is expired (no-op otherwise). */
    public function refreshIfNeeded(ConnectedApp $conn): void;

    /**
     * Push a captured lead/contact to the provider.
     *
     * @param array<string,mixed> $lead
     * @return array<string,mixed> provider record info (best-effort)
     */
    public function pushContact(ConnectedApp $conn, array $lead): array;

    /**
     * Pull contacts inbound, yielding normalized arrays. Calls $onCursor with
     * the new incremental cursor once the run completes.
     *
     * @return iterable<array<string,mixed>>
     */
    public function pullContacts(ConnectedApp $conn, ?callable $onCursor = null): iterable;
}
