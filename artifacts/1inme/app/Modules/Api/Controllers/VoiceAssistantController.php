<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\Voice\VoiceAssistantService;
use App\Services\AI\Voice\VoiceToolRegistry;
use App\Services\AI\WhisperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        protected WhisperService $whisper,
    ) {}

    /**
     * Hardcoded list of normalised wake-phrase variants. We accept a
     * fairly permissive set because Whisper renders "1INME" in many
     * different ways depending on accent and audio quality.
     *
     * Each entry is already lowercased + alphanumeric/space only so it
     * can be substring-matched against the normalised transcript.
     */
    public const WAKE_PHRASES = [
        'hey 1inme',
        'hey one inme',
        'hey one in me',
        'hey 1 in me',
        'hey oneinme',
        'hi 1inme',
        'hi one inme',
        'ok 1inme',
        'okay 1inme',
        'okay one inme',
        'a 1inme',
    ];

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

    /**
     * Lightweight wake-phrase detector for the mobile app's foreground
     * "Hey 1INME" listener. Runs Whisper without billing the user's
     * credit ledger so a continuously listening client doesn't drain
     * voice_stt credits — the user only pays when a real turn fires.
     *
     * Heavily throttled at the route level so a misbehaving client
     * can't spin and rack up Whisper API costs on us.
     */
    public function wakeCheck(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!AiEngineSettings::voiceAllowedFor($user)) {
            return response()->json(['matched' => false, 'reason' => 'plan'], 403);
        }

        $request->validate([
            // Wake snippets are tiny — cap aggressively to keep
            // Whisper costs predictable even with a chatty client.
            'audio' => 'required|file|mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/3gpp,audio/amr,application/octet-stream|max:512',
        ]);

        try {
            $stt = $this->whisper->transcribe($user, $request->file('audio'), [
                'no_charge' => true,
                'meta'      => ['kind' => 'wake_check'],
            ]);
        } catch (\Throwable $e) {
            // Wake checks must never bubble errors to the client — a
            // transient Whisper hiccup just means "no match this round".
            Log::info('Voice wake-check failed: ' . $e->getMessage());
            return response()->json(['matched' => false, 'transcript' => '']);
        }

        $transcript = (string) ($stt['text'] ?? '');
        $matched    = $this->matchesWakePhrase($transcript);

        return response()->json([
            'matched'    => $matched,
            'transcript' => $transcript,
        ]);
    }

    /**
     * Normalise the transcript (lowercase, strip punctuation, collapse
     * whitespace) and check whether any of the accepted wake-phrase
     * variants appears as a substring.
     */
    public function matchesWakePhrase(string $transcript): bool
    {
        $norm = strtolower($transcript);
        // Replace anything that isn't a-z/0-9 with a space, then collapse.
        $norm = preg_replace('/[^a-z0-9]+/', ' ', $norm) ?? '';
        $norm = trim(preg_replace('/\s+/', ' ', $norm) ?? '');
        if ($norm === '') return false;

        foreach (self::WAKE_PHRASES as $phrase) {
            if (str_contains($norm, $phrase)) return true;
        }
        return false;
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
                'Wake word ("Hey 1INME") only listens while the app is open in the foreground — it can\'t wake the phone or run in the background.',
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
        if (!$user) return false;
        if (!empty($user->is_admin)) return true;
        if (method_exists($user, 'hasPermission') && $user->hasPermission('user.platform.admin')) {
            return true;
        }
        return false;
    }
}
