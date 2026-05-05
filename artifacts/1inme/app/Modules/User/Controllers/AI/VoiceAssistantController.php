<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\Voice\VoiceAssistantService;
use App\Services\AI\Voice\VoiceToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP entry points for the web Voice Assistant.
 *
 *   POST /user/ai/voice/turn
 *     Multipart upload: `audio` blob + JSON `context` (prior messages,
 *     confirmed_tools). Returns transcript, spoken reply (base64 mp3),
 *     tool results, pending confirmations, credit breakdown, and the
 *     updated balance.
 *
 *   GET  /user/ai/voice/capabilities
 *     Returns the live tool catalogue (filtered by the caller's
 *     permissions) and the explicit out-of-scope limitations so the
 *     "What I can do / can't do" panel never drifts from reality.
 *
 * Throttling is applied at the route level (turns_per_minute from
 * AiEngineSettings).
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
            'audio'   => 'required|file|mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a,application/octet-stream|max:20480',
            'context' => 'nullable|string',
        ]);

        $context = [];
        if (!empty($data['context'])) {
            $decoded = json_decode($data['context'], true);
            if (is_array($decoded)) $context = $decoded;
        }

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
        $tools = $this->tools->visibleTo($user, $isAdmin);

        // Group by category for the help panel.
        $grouped = [];
        foreach ($tools as $name => $spec) {
            $grouped[$spec['category']][] = ['name' => $name] + $spec;
        }

        return response()->json([
            'enabled'      => AiEngineSettings::voiceAllowedFor($user),
            'balance'      => $this->credits->getBalance($user),
            'rate_limit'   => AiEngineSettings::voiceTurnsPerMinute(),
            'pricing'      => [
                'stt_credits_per_minute'    => AiEngineSettings::voiceSttCreditsPerMinute(),
                'tts_credits_per_1k_chars'  => AiEngineSettings::voiceTtsCreditsPer1kChars(),
            ],
            'tools'        => $grouped,
            'limitations'  => [
                'No phone calls or outbound dialing — voice only runs inside the web app.',
                'Wake word listening only works on the mobile app while it\'s open in the foreground — the web app still requires you to press the mic.',
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
