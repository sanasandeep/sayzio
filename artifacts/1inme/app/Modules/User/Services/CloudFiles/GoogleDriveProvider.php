<?php

namespace App\Modules\User\Services\CloudFiles;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveProvider implements CloudProvider
{
    public function key(): string { return 'google_drive'; }

    public function authorizeUrl(CloudProviderApp $app, string $state, string $redirectUri): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'              => $app->client_id,
            'redirect_uri'           => $redirectUri,
            'response_type'          => 'code',
            'scope'                  => 'https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.email',
            'access_type'            => 'offline',
            'prompt'                 => 'consent',
            'include_granted_scopes' => 'true',
            'state'                  => $state,
        ]);
    }

    public function exchangeCode(CloudProviderApp $app, string $code, string $redirectUri): array
    {
        $r = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $app->client_id,
            'client_secret' => (string) $app->client_secret_encrypted,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);
        if (!$r->ok()) throw new RuntimeException('Google token exchange failed: ' . $r->body());
        $j = $r->json();
        $email = null;
        $profile = Http::withToken($j['access_token'])->get('https://www.googleapis.com/oauth2/v2/userinfo');
        if ($profile->ok()) $email = $profile->json('email');

        return [
            $j['access_token'],
            $j['refresh_token'] ?? null,
            isset($j['expires_in']) ? now()->addSeconds((int) $j['expires_in']) : null,
            $email,
            $email,
            isset($j['scope']) ? explode(' ', $j['scope']) : [],
        ];
    }

    public function refreshAccessToken(CloudProviderApp $app, CloudConnection $conn): array
    {
        if (!$conn->refresh_token_encrypted) throw new RuntimeException('No refresh token stored.');
        $r = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => $app->client_id,
            'client_secret' => (string) $app->client_secret_encrypted,
            'refresh_token' => (string) $conn->refresh_token_encrypted,
            'grant_type'    => 'refresh_token',
        ]);
        if (!$r->ok()) throw new RuntimeException('Google token refresh failed: ' . $r->body());
        $j = $r->json();
        return [
            $j['access_token'],
            isset($j['expires_in']) ? now()->addSeconds((int) $j['expires_in']) : null,
            $j['refresh_token'] ?? null,
        ];
    }

    public function listFolder(CloudConnection $conn, ?string $folderId, ?string $cursor = null): array
    {
        $parent = $folderId ?: 'root';
        $params = [
            'q'        => "'" . str_replace("'", "\\'", $parent) . "' in parents and trashed=false",
            'fields'   => 'nextPageToken,files(id,name,mimeType,size,webViewLink,thumbnailLink,iconLink)',
            'pageSize' => 100,
        ];
        if ($cursor) $params['pageToken'] = $cursor;

        $r = Http::withToken((string) $conn->access_token_encrypted)
            ->get('https://www.googleapis.com/drive/v3/files', $params);
        if (!$r->ok()) throw new RuntimeException('Google list failed: ' . $r->body());
        $j = $r->json();

        $folders = [];
        $files = [];
        foreach (($j['files'] ?? []) as $f) {
            if (($f['mimeType'] ?? '') === 'application/vnd.google-apps.folder') {
                $folders[] = ['id' => $f['id'], 'name' => $f['name']];
            } else {
                $files[] = [
                    'id'            => $f['id'],
                    'name'          => $f['name'],
                    'mime'          => $f['mimeType'] ?? null,
                    'size'          => (int) ($f['size'] ?? 0),
                    'link'          => $f['webViewLink'] ?? ('https://drive.google.com/file/d/' . $f['id'] . '/view'),
                    'thumbnail_url' => $f['thumbnailLink'] ?? ($f['iconLink'] ?? null),
                ];
            }
        }
        return ['folders' => $folders, 'files' => $files, 'cursor' => $j['nextPageToken'] ?? null];
    }

    public function search(CloudConnection $conn, string $query, ?string $cursor = null): array
    {
        $q = "name contains '" . str_replace("'", "\\'", $query) . "' and trashed=false and mimeType != 'application/vnd.google-apps.folder'";
        $params = [
            'q'        => $q,
            'fields'   => 'nextPageToken,files(id,name,mimeType,size,webViewLink,thumbnailLink,iconLink)',
            'pageSize' => 100,
        ];
        if ($cursor) $params['pageToken'] = $cursor;

        $r = Http::withToken((string) $conn->access_token_encrypted)
            ->get('https://www.googleapis.com/drive/v3/files', $params);
        if (!$r->ok()) throw new RuntimeException('Google search failed: ' . $r->body());
        $j = $r->json();
        $files = [];
        foreach (($j['files'] ?? []) as $f) {
            $files[] = [
                'id'            => $f['id'],
                'name'          => $f['name'],
                'mime'          => $f['mimeType'] ?? null,
                'size'          => (int) ($f['size'] ?? 0),
                'link'          => $f['webViewLink'] ?? ('https://drive.google.com/file/d/' . $f['id'] . '/view'),
                'thumbnail_url' => $f['thumbnailLink'] ?? ($f['iconLink'] ?? null),
            ];
        }
        return ['folders' => [], 'files' => $files, 'cursor' => $j['nextPageToken'] ?? null];
    }
}
