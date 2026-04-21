<?php

namespace App\Modules\User\Services\CloudFiles;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use RuntimeException;

class CloudProviderRegistry
{
    /** @var array<string,CloudProvider> */
    protected array $providers;

    public function __construct(GoogleDriveProvider $g, DropboxProvider $d, OneDriveProvider $o)
    {
        $this->providers = [
            $g->key() => $g,
            $d->key() => $d,
            $o->key() => $o,
        ];
    }

    public function get(string $key): CloudProvider
    {
        if (!isset($this->providers[$key])) {
            throw new RuntimeException("Unknown cloud provider: {$key}");
        }
        return $this->providers[$key];
    }

    public function refreshIfExpiring(CloudConnection $conn, CloudProviderApp $app): CloudConnection
    {
        if (!$conn->expiresSoon()) return $conn;
        if (!$conn->refresh_token_encrypted) return $conn;

        try {
            [$access, $expires, $newRefresh] = $this->get($conn->provider)->refreshAccessToken($app, $conn);
        } catch (RuntimeException $e) {
            $conn->last_error = substr($e->getMessage(), 0, 240);
            $conn->save();
            return $conn;
        }

        $conn->access_token_encrypted = $access;
        $conn->expires_at = $expires;
        if ($newRefresh) $conn->refresh_token_encrypted = $newRefresh;
        $conn->last_error = null;
        $conn->last_synced_at = now();
        $conn->save();
        return $conn;
    }
}
