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
 * Persona — generate a concise audience / brand persona from a short
 * brief. Useful before writing copy: tone, voice, do's and don'ts.
 *
 *   GET  /user/ai/persona            form + last persona (session)
 *   POST /user/ai/persona/generate   runs the chat call
 *
 * Spend is tagged `feature => 'persona'` (with a sub-reason of
 * "persona.profile" in the ledger row) so admin reporting can bucket
 * generated profiles separately if we add more persona shapes later.
 */
class PersonaController extends Controller
{
    protected const MODEL = 'gpt-4o-mini';

    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
    ) {}

    public function show(Request $request)
    {
        $this->ensureEnabled();
        return view('user.ai.persona', [
            'balance' => $this->credits->getBalance($request->user()),
            'result'  => session('ai.persona.result'),
            'input'   => session('ai.persona.input', []),
        ]);
    }

    public function generate(Request $request)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'audience' => 'required|string|min:3|max:400',
            'goals'    => 'nullable|string|max:600',
            'tone'     => 'nullable|string|max:200',
        ]);

        $brief = "Audience: {$data['audience']}\n"
            . "Goals: " . ($data['goals'] ?? 'unspecified') . "\n"
            . "Preferred tone: " . ($data['tone'] ?? 'unspecified');

        $messages = [
            ['role' => 'system', 'content' =>
                "You are Persona, a brand strategist. Given a short brief, "
                . "output a markdown persona profile with these sections:\n"
                . "**Snapshot** (2-3 sentences)\n"
                . "**Traits** (5 bullet adjectives)\n"
                . "**Voice & tone** (3 short rules)\n"
                . "**Avoid** (3 short rules).\n"
                . "Keep it under 200 words."],
            ['role' => 'user', 'content' => $brief],
        ];

        try {
            $out = $this->ai->chat($request->user(), self::MODEL, $messages, [
                'feature'     => 'persona.profile',
                'temperature' => 0.6,
                'max_tokens'  => 500,
                'reason'      => 'Persona: profile generation',
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientAiCreditsException) throw $e;
            Log::warning('Persona AI call failed: ' . $e->getMessage());
            return back()->withInput()->with('error',
                'Persona could not respond right now. Please try again.');
        }

        session()->flash('ai.persona.input', $data);
        session()->flash('ai.persona.result', [
            'content'       => $out['content'],
            'credits_spent' => $out['credits_spent'],
            'model'         => $out['model'],
        ]);
        return redirect()->route('user.ai.persona.show');
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
