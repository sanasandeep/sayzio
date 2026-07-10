<?php

namespace App\Services\WhatsApp;

use App\Services\Integrations\IntegrationKeySettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper over the WhatsApp Cloud API for the two-way agent
 * (Task #2759). Handles the three calls the inbound flow needs —
 * sending a free-form session reply, resolving a media id to a
 * download URL, and downloading the bytes — plus inbound signature
 * verification.
 *
 * Credentials come from IntegrationKeySettings (admin value first,
 * then config/whatsapp.php env). When delivery credentials are absent
 * the client degrades to preview mode: replies are logged, never sent,
 * so the agent can be wired up and exercised without live Meta access.
 */
class WhatsAppCloudApi
{
    /** True when we can actually send/download (vs. preview-mode logging). */
    public function configured(): bool
    {
        return IntegrationKeySettings::whatsappConfigured();
    }

    /**
     * Send a free-form text reply. WhatsApp allows free-form session
     * messages within 24h of the user's last inbound message, which is
     * always the case here (we only reply to people who just messaged us).
     */
    public function sendText(string $to, string $body): bool
    {
        $phoneNumberId = (string) (IntegrationKeySettings::whatsappPhoneNumberId() ?? '');
        $accessToken   = (string) (IntegrationKeySettings::whatsappAccessToken() ?? '');
        $to            = $this->normalizePhone($to);

        if ($phoneNumberId === '' || $accessToken === '' || $to === '') {
            Log::info('WhatsApp agent (preview mode — credentials absent): reply to ' . $this->maskPhone($to) . ': ' . $body);
            return false;
        }

        // WhatsApp text bodies cap at 4096 chars.
        $body = mb_substr($body, 0, 4096);
        $endpoint = $this->endpoint($phoneNumberId, 'messages');

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'text',
                    'text'              => ['preview_url' => true, 'body' => $body],
                ]);

            if ($response->failed()) {
                Log::warning('WhatsApp agent reply failed: HTTP ' . $response->status() . ' ' . $response->body());
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp agent reply threw: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve an inbound media id to a (short-lived) download URL.
     * Returns ['url' => string, 'mime' => ?string] or null on failure.
     */
    public function getMediaUrl(string $mediaId): ?array
    {
        $accessToken = (string) (IntegrationKeySettings::whatsappAccessToken() ?? '');
        if ($accessToken === '' || $mediaId === '') return null;

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->get($this->endpoint($mediaId, ''));

            if ($response->failed()) {
                Log::warning('WhatsApp media lookup failed: HTTP ' . $response->status() . ' ' . $response->body());
                return null;
            }

            $url = (string) ($response->json('url') ?? '');
            if ($url === '') return null;

            return [
                'url'  => $url,
                'mime' => $response->json('mime_type'),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media lookup threw: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download media bytes from a graph media URL. The URL itself
     * requires the bearer token (it is not a public CDN URL).
     */
    public function downloadMedia(string $url): ?string
    {
        $accessToken = (string) (IntegrationKeySettings::whatsappAccessToken() ?? '');
        if ($accessToken === '' || $url === '') return null;

        try {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get($url);

            if ($response->failed()) {
                Log::warning('WhatsApp media download failed: HTTP ' . $response->status());
                return null;
            }
            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('WhatsApp media download threw: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate Meta's X-Hub-Signature-256 header against the raw request
     * body using the configured app secret.
     *
     * Returns false — rejecting the request — when no app secret is
     * configured. This is a deliberate fail-closed design: without a
     * signing secret the server cannot authenticate inbound payloads, so
     * accepting them would allow anyone to forge arbitrary WhatsApp messages
     * and drive authenticated agent actions. Administrators must supply the
     * app secret before the webhook will process any messages.
     */
    public function verifySignature(?string $signatureHeader, string $rawBody): bool
    {
        $appSecret = (string) (IntegrationKeySettings::whatsappAppSecret() ?? '');
        if ($appSecret === '') {
            // No secret configured — cannot authenticate the payload. Fail closed.
            return false;
        }
        if (!is_string($signatureHeader) || !str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        return hash_equals($expected, $signatureHeader);
    }

    /** True only when an app secret is set (signatures are actually checked). */
    public function signatureEnforced(): bool
    {
        return (string) (IntegrationKeySettings::whatsappAppSecret() ?? '') !== '';
    }

    private function endpoint(string $idOrPath, string $suffix): string
    {
        $version = IntegrationKeySettings::whatsappGraphVersion();
        $path = $suffix === '' ? $idOrPath : "{$idOrPath}/{$suffix}";
        return "https://graph.facebook.com/{$version}/{$path}";
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function maskPhone(string $phone): string
    {
        return strlen($phone) > 4 ? str_repeat('•', strlen($phone) - 4) . substr($phone, -4) : $phone;
    }
}
