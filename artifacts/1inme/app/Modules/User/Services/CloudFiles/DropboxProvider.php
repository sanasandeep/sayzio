<?php

namespace App\Modules\User\Services\CloudFiles;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DropboxProvider implements CloudProvider
{
    public function key(): string { return 'dropbox'; }

    public function authorizeUrl(CloudProviderApp $app, string $state, string $redirectUri): string
    {
        return 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
            'client_id'      => $app->client_id,
            'redirect_uri'   => $redirectUri,
            'response_type'  => 'code',
            'token_access_type' => 'offline',
            'state'          => $state,
        ]);
    }

    public function exchangeCode(CloudProviderApp $app, string $code, string $redirectUri): array
    {
        $r = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'client_id'     => $app->client_id,
            'client_secret' => (string) $app->client_secret_encrypted,
            'redirect_uri'  => $redirectUri,
        ]);
        if (!$r->ok()) throw new RuntimeException('Dropbox token exchange failed: ' . $r->body());
        $j = $r->json();

        $email = null; $name = null;
        $acc = Http::withToken($j['access_token'])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post('https://api.dropboxapi.com/2/users/get_current_account', '');
        if ($acc->ok()) {
            $email = $acc->json('email');
            $name  = $acc->json('name.display_name') ?: $email;
        }

        return [
            $j['access_token'],
            $j['refresh_token'] ?? null,
            isset($j['expires_in']) ? now()->addSeconds((int) $j['expires_in']) : null,
            $email,
            $name ?: $email,
            isset($j['scope']) ? explode(' ', $j['scope']) : [],
        ];
    }

    public function refreshAccessToken(CloudProviderApp $app, CloudConnection $conn): array
    {
        if (!$conn->refresh_token_encrypted) throw new RuntimeException('No refresh token stored.');
        $r = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => (string) $conn->refresh_token_encrypted,
            'client_id'     => $app->client_id,
            'client_secret' => (string) $app->client_secret_encrypted,
        ]);
        if (!$r->ok()) throw new RuntimeException('Dropbox token refresh failed: ' . $r->body());
        $j = $r->json();
        return [
            $j['access_token'],
            isset($j['expires_in']) ? now()->addSeconds((int) $j['expires_in']) : null,
            $j['refresh_token'] ?? null,
        ];
    }

    public function listFolder(CloudConnection $conn, ?string $folderId, ?string $cursor = null): array
    {
        // Dropbox uses paths, not IDs. Empty string = root.
        $path = $folderId ?: '';

        if ($cursor) {
            $r = Http::withToken((string) $conn->access_token_encrypted)
                ->post('https://api.dropboxapi.com/2/files/list_folder/continue', ['cursor' => $cursor]);
        } else {
            $r = Http::withToken((string) $conn->access_token_encrypted)
                ->post('https://api.dropboxapi.com/2/files/list_folder', [
                    'path'      => $path,
                    'recursive' => false,
                    'limit'     => 200,
                ]);
        }
        if (!$r->ok()) throw new RuntimeException('Dropbox list failed: ' . $r->body());
        $j = $r->json();

        $folders = []; $files = [];
        foreach (($j['entries'] ?? []) as $e) {
            $tag = $e['.tag'] ?? '';
            if ($tag === 'folder') {
                $folders[] = ['id' => $e['path_lower'] ?? $e['path_display'], 'name' => $e['name']];
            } elseif ($tag === 'file') {
                $files[] = [
                    'id'            => $e['id'],
                    'name'          => $e['name'],
                    'mime'          => null,
                    'size'          => (int) ($e['size'] ?? 0),
                    'link'          => 'https://www.dropbox.com/preview' . ($e['path_display'] ?? ''),
                    'thumbnail_url' => null,
                ];
            }
        }
        return ['folders' => $folders, 'files' => $files, 'cursor' => ($j['has_more'] ?? false) ? ($j['cursor'] ?? null) : null];
    }
}
