<?php

namespace App\Modules\User\Services\CloudFiles;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OneDriveProvider implements CloudProvider
{
    public function key(): string { return 'onedrive'; }

    public function authorizeUrl(CloudProviderApp $app, string $state, string $redirectUri): string
    {
        return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query([
            'client_id'     => $app->client_id,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'query',
            'scope'         => 'offline_access Files.Read User.Read',
            'state'         => $state,
        ]);
    }

    public function exchangeCode(CloudProviderApp $app, string $code, string $redirectUri): array
    {
        $r = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'code'          => $code,
            'client_id'     => $app->client_id,
            'client_secret' => (string) $app->client_secret_encrypted,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
            'scope'         => 'offline_access Files.Read User.Read',
        ]);
        if (!$r->ok()) throw new RuntimeException('OneDrive token exchange failed: ' . $r->body());
        $j = $r->json();

        $email = null; $name = null;
        $me = Http::withToken($j['access_token'])->get('https://graph.microsoft.com/v1.0/me');
        if ($me->ok()) {
            $email = $me->json('mail') ?: $me->json('userPrincipalName');
            $name  = $me->json('displayName') ?: $email;
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
        $r = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id'     => $app->client_id,
            'client_secret' => (string) $app->client_secret_encrypted,
            'refresh_token' => (string) $conn->refresh_token_encrypted,
            'grant_type'    => 'refresh_token',
            'scope'         => 'offline_access Files.Read User.Read',
        ]);
        if (!$r->ok()) throw new RuntimeException('OneDrive token refresh failed: ' . $r->body());
        $j = $r->json();
        return [
            $j['access_token'],
            isset($j['expires_in']) ? now()->addSeconds((int) $j['expires_in']) : null,
            $j['refresh_token'] ?? null,
        ];
    }

    public function listFolder(CloudConnection $conn, ?string $folderId, ?string $cursor = null): array
    {
        if ($cursor) {
            // Microsoft Graph @odata.nextLink is a fully-qualified URL. Treating
            // it as user input would let a client coerce a server-side request
            // to any host with the OAuth bearer attached (SSRF + token leak),
            // so we hard-pin the host to graph.microsoft.com.
            $host = parse_url($cursor, PHP_URL_HOST);
            $scheme = parse_url($cursor, PHP_URL_SCHEME);
            if ($scheme !== 'https' || $host !== 'graph.microsoft.com') {
                throw new RuntimeException('Refusing untrusted OneDrive cursor URL.');
            }
            $url = $cursor;
        } else {
            $segment = $folderId ? ('items/' . rawurlencode($folderId)) : 'root';
            $url = 'https://graph.microsoft.com/v1.0/me/drive/' . $segment . '/children?$top=200';
        }
        $r = Http::withToken((string) $conn->access_token_encrypted)->get($url);
        if (!$r->ok()) throw new RuntimeException('OneDrive list failed: ' . $r->body());
        $j = $r->json();

        $folders = []; $files = [];
        foreach (($j['value'] ?? []) as $e) {
            if (isset($e['folder'])) {
                $folders[] = ['id' => $e['id'], 'name' => $e['name']];
            } elseif (isset($e['file'])) {
                $files[] = [
                    'id'            => $e['id'],
                    'name'          => $e['name'],
                    'mime'          => $e['file']['mimeType'] ?? null,
                    'size'          => (int) ($e['size'] ?? 0),
                    'link'          => $e['webUrl'] ?? '',
                    'thumbnail_url' => $e['thumbnails'][0]['medium']['url'] ?? null,
                ];
            }
        }
        return ['folders' => $folders, 'files' => $files, 'cursor' => $j['@odata.nextLink'] ?? null];
    }
}
