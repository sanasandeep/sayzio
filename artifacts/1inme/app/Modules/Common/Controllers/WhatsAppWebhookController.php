<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\HandleWhatsAppInboundJob;
use App\Services\Integrations\IntegrationKeySettings;
use App\Services\WhatsApp\WhatsAppCloudApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound webhook for the two-way WhatsApp AI agent.
 *
 *   GET  /webhooks/whatsapp  — Meta's verification handshake. Echoes the
 *        hub.challenge back when hub.verify_token matches the configured
 *        verify token.
 *   POST /webhooks/whatsapp  — inbound message delivery. Validates the
 *        X-Hub-Signature-256 HMAC; the app secret MUST be configured or
 *        every POST is rejected (fail-closed). Accepted payloads are
 *        queued immediately so Meta never retries on a slow model turn.
 *
 * Routes are CSRF-exempt via the `webhooks/*` rule in bootstrap/app.php.
 */
class WhatsAppWebhookController extends Controller
{
    /** Meta verification handshake. */
    public function verify(Request $request)
    {
        $mode      = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token     = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expected = IntegrationKeySettings::whatsappWebhookVerifyToken();

        if ($mode === 'subscribe' && $expected !== null && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** Inbound message delivery. */
    public function ingest(Request $request, WhatsAppCloudApi $cloud)
    {
        // Always answer 200 quickly so Meta doesn't retry; bail out early
        // (still 200) when the agent is off or the signature is invalid.
        if (!IntegrationKeySettings::whatsappAgentEnabled()) {
            return response()->json(['ok' => true]);
        }

        $raw = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (!$cloud->verifySignature($signature, $raw)) {
            // verifySignature() returns false both for invalid signatures AND
            // when no app secret is configured (fail-closed). Return 200 to
            // prevent Meta from retrying payloads we will never accept.
            if (!$cloud->signatureEnforced()) {
                Log::warning('WhatsApp webhook: app secret not configured — all POST traffic rejected until a secret is set.');
            } else {
                Log::warning('WhatsApp webhook: invalid signature, rejecting payload.');
            }
            return response()->json(['ok' => true]);
        }

        $payload = $request->json()->all();

        foreach ($this->extractMessages($payload) as $msg) {
            $from = preg_replace('/\D+/', '', (string) ($msg['from'] ?? '')) ?? '';
            if ($from === '') continue;
            HandleWhatsAppInboundJob::dispatch($from, $msg);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Flatten the WhatsApp Cloud API envelope down to the individual
     * inbound message entries. Statuses (delivery receipts) and other
     * non-message change types are ignored.
     *
     * @return array<int,array<string,mixed>>
     */
    private function extractMessages(array $payload): array
    {
        $messages = [];
        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                foreach (($value['messages'] ?? []) as $message) {
                    if (is_array($message)) $messages[] = $message;
                }
            }
        }
        return $messages;
    }
}
