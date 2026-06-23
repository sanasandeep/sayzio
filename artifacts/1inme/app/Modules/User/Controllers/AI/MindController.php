<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Mind — paste a stream of thoughts / meeting notes / brain-dump and
 * the AI returns a tight summary + actionable next steps.
 *
 *   GET  /user/ai/mind          form + last result (session-scoped)
 *   POST /user/ai/mind/think    runs the chat call, charges coins
 *
 * Spend is tagged `feature => 'mind'` so /admin/ai-usage can attribute
 * cost back to this product. Insufficient-coin responses bubble up
 * via the global exception handler which redirects to the wallet
 * top-up page with a clear CTA.
 */
class MindController extends Controller
{
    public function __construct(
        protected OpenAiService $ai,
        protected AiUsageCharger $credits,
    ) {}

    public function show(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Mind']);
        }
        $this->ensureEnabled();
        return view('user.ai.mind', [
            'balance' => $this->credits->getBalance($request->user()),
            'result'  => session('ai.mind.result'),
            'input'   => session('ai.mind.input', ''),
        ]);
    }

    public function think(Request $request)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'thoughts' => 'required|string|min:5|max:8000',
        ]);

        $messages = [
            ['role' => 'system', 'content' =>
                "You are Mind, a focused thinking partner. Read the user's "
                . "raw thoughts and reply with: a 2-3 sentence summary, then "
                . "a short bulleted list of the clearest next actions. Be "
                . "concise — no preamble."],
            ['role' => 'user', 'content' => $data['thoughts']],
        ];

        try {
            $out = $this->ai->chat($request->user(), AiEngineSettings::featureModel('mind'), $messages, [
                'feature'     => 'mind',
                'temperature' => 0.4,
                'max_tokens'  => 600,
                'reason'      => 'Mind: organize thoughts',
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientCoinsForAiException) throw $e;
            Log::warning('Mind AI call failed: ' . $e->getMessage());
            return back()->withInput()->with('error',
                'Mind could not respond right now. Please try again.');
        }

        session()->flash('ai.mind.input', $data['thoughts']);
        session()->flash('ai.mind.result', [
            'content'       => $out['content'],
            'credits_spent' => $out['credits_spent'],
            'model'         => $out['model'],
        ]);
        return redirect()->route('user.ai.mind.show');
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
