<?php

namespace App\Modules\User\Services\CloudFiles;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;

/**
 * A thin OAuth + folder-listing client for one cloud-storage provider.
 * All implementations talk HTTP directly via the Http facade so they
 * can be Http::fake()'d in tests without bringing in vendor SDKs.
 */
interface CloudProvider
{
    public function key(): string;

    /** Browser-bound URL the user is sent to for consent. */
    public function authorizeUrl(CloudProviderApp $app, string $state, string $redirectUri): string;

    /**
     * Exchange the auth code for tokens + account profile. Returns:
     *   [access_token, refresh_token|null, expires_at|null, account_email|null, account_label|null, scopes[]]
     */
    public function exchangeCode(CloudProviderApp $app, string $code, string $redirectUri): array;

    /**
     * Refresh the access token. Returns [access_token, expires_at|null, refresh_token|null]
     * (refresh_token may be returned by some providers; null means keep the old one).
     */
    public function refreshAccessToken(CloudProviderApp $app, CloudConnection $conn): array;

    /**
     * List one folder. Returns:
     *   ['folders' => [['id','name']], 'files' => [['id','name','mime','size','link','thumbnail_url']], 'cursor' => string|null]
     */
    public function listFolder(CloudConnection $conn, ?string $folderId, ?string $cursor = null): array;
}
