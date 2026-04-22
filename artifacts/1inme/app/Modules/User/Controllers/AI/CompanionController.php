<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Companion — multi-turn chat assistant. History is held in the user's
 * session (no DB table) so the feature is self-contained: ship today,
 * graduate to persisted threads later if usage warrants it.
 *
 *   GET  /user/ai/companion          chat UI + history
 *   POST /user/ai/companion/send     append user message + AI reply
 *   POST /user/ai/companion/reset    clear the conversation
 *
 * Spend is tagged `feature => 'companion'` for admin reporting.
 */
class CompanionController extends Controller
{
    protected const MODEL = 'gpt-4o-mini';
    protected const SESSION_KEY = 'ai.companion.history';

    /** Cap turns to keep prompt size — and per-call cost — predictable. */
    protected const MAX_TURNS = 12;

    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
    ) {}

    public function show(Request $request)
    {
        $this->ensureEnabled();
        return view('user.ai.companion', [
            'balance' => $this->credits->getBalance($request->user()),
            'history' => session(self::SESSION_KEY, []),
        ]);
    }

    public function send(Request $request)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $history = (array) session(self::SESSION_KEY, []);
        $history[] = ['role' => 'user', 'content' => $data['message']];

        // Trim to the last MAX_TURNS exchanges (assistant + user pairs)
        // so the running prompt doesn't balloon over a long conversation.
        $trimmed = array_slice($history, -1 * (self::MAX_TURNS * 2));

        $messages = array_merge(
            [['role' => 'system', 'content' =>
                "You are Companion, a friendly and concise assistant. "
                . "Keep replies clear and short unless the user asks for depth."]],
            $trimmed,
        );

        try {
            $out = $this->ai->chat($request->user(), self::MODEL, $messages, [
                'feature'     => 'companion.chat',
                'temperature' => 0.7,
                'max_tokens'  => 600,
                'reason'      => 'Companion: chat reply',
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientAiCreditsException) throw $e;
            Log::warning('Companion AI call failed: ' . $e->getMessage());
            // The user's turn was only appended to a local copy of the
            // history above and never persisted back to the session, so
            // returning here naturally leaves the saved transcript
            // unchanged — the user can retry without a dangling message.
            return back()->with('error',
                'Companion could not reply right now. Please try again.');
        }

        $history[] = [
            'role'    => 'assistant',
            'content' => $out['content'],
            'meta'    => ['credits_spent' => $out['credits_spent']],
        ];

        // Persist (not flash) — chat needs to stick across requests.
        session()->put(self::SESSION_KEY, array_slice($history, -1 * (self::MAX_TURNS * 2)));
        return redirect()->route('user.ai.companion.show');
    }

    public function reset(Request $request)
    {
        session()->forget(self::SESSION_KEY);
        return redirect()->route('user.ai.companion.show')
            ->with('status', 'Conversation cleared.');
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
