<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
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
        protected AiUsageCharger $credits,
    ) {}

    /**
     * The Voice Assistant itself runs as a floating mic widget on every
     * page, so this surface only exists to gate it gracefully: when the
     * engine is off (admin job) or the user's plan blocks voice, show the
     * shared self-serve gate page (upgrade + coin top-up) instead of a
     * silent widget or a bare 403. Allowed users are bounced to the
     * dashboard where the floating mic is already live.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // Engine off — master AI switch or the voice feature toggle. Only
        // an administrator can turn it on, so render the engine-off branch
        // (explainer + request access / admin enable) of the gate page.
        if (!AiEngineSettings::isEnabled() || !AiEngineSettings::voiceEnabled()) {
            return view('user.ai.disabled', [
                'title'     => 'Voice Assistant',
                'aiEnabled' => false,
            ]);
        }

        // Engine on but the user's plan doesn't unlock voice. Point them
        // at the cheapest plan that does so they can self-serve.
        if (!AiEngineSettings::voiceAllowedFor($user)) {
            return view('user.ai.disabled', [
                'title'       => 'Voice Assistant',
                'upgradePlan' => AiEngineSettings::voiceUpgradePlanFor($user),
            ]);
        }

        // Allowed: the mic widget is available everywhere already.
        return redirect()->route('user.dashboard');
    }

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
        } catch (InsufficientCoinsForAiException $e) {
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
                'stt_coins_per_minute'    => AiEngineSettings::voiceSttCoinsPerMinute(),
                'tts_coins_per_1k_chars'  => AiEngineSettings::voiceTtsCoinsPer1kChars(),
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
