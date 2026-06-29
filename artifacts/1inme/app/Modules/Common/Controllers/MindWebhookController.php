<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\IngestAiMindSourceJob;
use App\Modules\User\Models\AiMindSource;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public inbound endpoint for AI Mind "webhook" sources. Third-party
 * systems POST content here; we authenticate via the per-source signing
 * token, store the payload as the source body, and queue ingestion.
 *
 * The route is CSRF-exempt and unauthenticated (no session) — security
 * comes solely from the constant-time token comparison.
 */
class MindWebhookController extends Controller
{
    public function ingest(Request $request, AiMindSource $source): JsonResponse
    {
        // The whole feature is gated by the AI engine toggle.
        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['error' => ['message' => 'AI features are disabled.']], 404);
        }

        if ($source->type !== AiMindSource::TYPE_WEBHOOK) {
            return response()->json(['error' => ['message' => 'Not a webhook source.']], 404);
        }

        // Don't accept deliveries into a disabled Mind.
        $mind = $source->mind()->first();
        if (!$mind || $mind->is_disabled) {
            return response()->json(['error' => ['message' => 'This Knowledge Base is unavailable.']], 404);
        }

        // Authenticate: accept the token from a header, query string, or
        // JSON body field — whichever the third-party system can send.
        $presented = (string) (
            $request->header('X-Mind-Webhook-Token')
            ?: $request->query('token')
            ?: $request->input('token')
            ?: ''
        );
        $expected = $source->webhookToken();
        if (!$expected || $presented === '' || !hash_equals($expected, $presented)) {
            return response()->json(['error' => ['message' => 'Invalid or missing webhook token.']], 401);
        }

        // Resolve the payload text. Prefer the raw request body so JSON
        // and plain text both come through verbatim; fall back to a
        // `content`/`text` field for form posts.
        $payload = (string) $request->getContent();
        if (trim($payload) === '') {
            $payload = (string) ($request->input('content') ?? $request->input('text') ?? '');
        }
        $payload = trim($payload);
        if ($payload === '') {
            return response()->json(['error' => ['message' => 'Empty payload — nothing to ingest.']], 422);
        }

        // Cap the stored payload so a runaway POST can't blow up the
        // database or embedding bill (the ingestor caps again on read).
        $max = (int) AiMindSettings::cap('max_text_chars');
        if ($max > 0 && mb_strlen($payload) > $max) {
            $payload = mb_substr($payload, 0, $max);
        }

        $source->markWebhookReceived();
        $source->forceFill([
            'body'           => $payload,
            'status'         => AiMindSource::STATUS_QUEUED,
            'status_message' => 'Webhook payload received — ingestion queued.',
            'meta'           => $source->meta,
        ])->save();

        IngestAiMindSourceJob::dispatch($source->id);

        return response()->json(['data' => ['received' => true]]);
    }
}
