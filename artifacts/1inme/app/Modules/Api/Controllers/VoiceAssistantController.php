<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\Voice\VoiceAssistantService;
use App\Services\AI\Voice\VoiceToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile parity for the web Voice Assistant. Mirrors
 * {@see \App\Modules\User\Controllers\AI\VoiceAssistantController}
 * but routed under /api/v1/ai/voice/* with Sanctum bearer auth so the
 * Expo app can hit the same orchestrator (Whisper → GPT tool loop →
 * ElevenLabs) and the credit ledger continues to record three rows per
 * turn (voice_stt / voice_llm / voice_tts).
 */
class VoiceAssistantController extends Controller
{
    public function __construct(
        protected VoiceAssistantService $voice,
        protected VoiceToolRegistry $tools,
        protected AiCreditService $credits,
    ) {}

    public function turn(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!AiEngineSettings::voiceAllowedFor($user)) {
            return response()->json([
                'error' => 'Voice Assistant is not available on your plan.',
            ], 403);
        }

        $data = $request->validate([
            // Mobile recorders may upload m4a/aac/mp4 (iOS default) or
            // 3gpp/amr (Android), so the accepted mime list is wider
            // than the web's MediaRecorder webm/ogg pair.
            'audio'   => 'required|file|mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/3gpp,audio/amr,application/octet-stream|max:20480',
            'context' => 'nullable|string',
        ]);

        $context = [];
        if (!empty($data['context'])) {
            $decoded = json_decode($data['context'], true);
            if (is_array($decoded)) $context = $decoded;
        }
        // Mobile clients always come through this controller, so flag
        // mobile-only tools (e.g. write_nfc_tag) as available.
        $context['client_kind'] = 'mobile';

        try {
            $result = $this->voice->runTurn(
                $user,
                $this->isAdmin($user),
                $request->file('audio'),
                $context,
            );
        } catch (InsufficientAiCreditsException $e) {
            return response()->json([
                'error'    => 'Out of AI credits — top up to keep using voice.',
                'balance'  => $this->credits->getBalance($user),
                'required' => $e->required,
            ], 402);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function capabilities(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = $this->isAdmin($user);
        $tools = $this->tools->visibleTo($user, $isAdmin, true);

        $grouped = [];
        foreach ($tools as $name => $spec) {
            $grouped[$spec['category']][] = ['name' => $name] + $spec;
        }

        return response()->json([
            'enabled'     => AiEngineSettings::voiceAllowedFor($user),
            'balance'     => $this->credits->getBalance($user),
            'rate_limit'  => AiEngineSettings::voiceTurnsPerMinute(),
            'pricing'     => [
                'stt_credits_per_minute'   => AiEngineSettings::voiceSttCreditsPerMinute(),
                'tts_credits_per_1k_chars' => AiEngineSettings::voiceTtsCreditsPer1kChars(),
            ],
            'tools'       => $grouped,
            'limitations' => [
                'No phone calls or outbound dialing — voice only runs inside the app.',
                'No always-listening wake word; tap the mic to start a turn.',
                'Cannot edit invoices, tax info, or other legal/billing documents.',
                'No raw SQL or database access — only the allow-listed tools above.',
                'Cannot deploy code, change infrastructure, or reset other users\' passwords.',
                'Does not run scheduled actions — it acts only when you speak.',
                'Languages limited to those supported by the configured Whisper and ElevenLabs models.',
            ],
        ]);
    }

    protected function isAdmin($user): bool
    {
        return (bool) ($user->is_admin ?? false)
            || (string) ($user->role ?? '') === 'admin'
            || (string) ($user->role ?? '') === 'super_admin';
    }
}
