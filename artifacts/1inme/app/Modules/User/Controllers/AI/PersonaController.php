<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersona;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Persona — generate a concise audience / brand persona from a short
 * brief. Useful before writing copy: tone, voice, do's and don'ts.
 *
 *   GET    /user/ai/persona               form + last persona + saved list
 *   POST   /user/ai/persona/generate      runs the chat call
 *   POST   /user/ai/persona/save          save the latest result to library
 *   PATCH  /user/ai/personas/{persona}    rename a saved persona
 *   DELETE /user/ai/personas/{persona}    delete a saved persona
 *
 * The form lets the user select one or more of their Minds (knowledge
 * bases) and optionally opt the platform default Mind in. When minds
 * are selected, Persona retrieves the most relevant chunks from those
 * Minds and grounds the generation in that context, returning the
 * cited sources alongside the persona profile.
 *
 * Spend is tagged `feature => 'persona'` (with a sub-reason of
 * "persona.profile" in the ledger row) so admin reporting can bucket
 * generated profiles separately if we add more persona shapes later.
 */
class PersonaController extends Controller
{
    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
        protected AiMindQueryService $minds,
    ) {}

    public function show(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        AiMindProvisioner::ensureForUser($user);

        // Optional ?from={id} pre-fills the form from a saved persona.
        $input = session('ai.persona.input', []);
        $fromId = $request->integer('from');
        if ($fromId && empty($input)) {
            $from = AiPersona::where('user_id', $user->id)->find($fromId);
            if ($from) {
                $input = [
                    'audience' => $from->audience,
                    'goals'    => $from->goals,
                    'tone'     => $from->tone,
                ];
            }
        }

        return view('user.ai.persona', [
            'balance'      => $this->credits->getBalance($user),
            'result'       => session('ai.persona.result'),
            'input'        => $input,
            'saved'        => AiPersona::where('user_id', $user->id)
                ->orderByDesc('updated_at')
                ->get(),
            'mineMinds'    => $this->userMinds($user),
            'platformMind' => $this->platformMind(),
        ]);
    }

    public function generate(Request $request)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $data = $request->validate([
            'audience'         => 'required|string|min:3|max:400',
            'goals'            => 'nullable|string|max:600',
            'tone'             => 'nullable|string|max:200',
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $brief = "Audience: {$data['audience']}\n"
            . "Goals: " . ($data['goals'] ?? 'unspecified') . "\n"
            . "Preferred tone: " . ($data['tone'] ?? 'unspecified');

        // Resolve any selected Minds (own + opt-in platform) and pull
        // matching context. Embedding spend is on the asking user.
        $mindIds = $data['mind_ids'] ?? [];
        $includePlatform = (bool) ($data['include_platform'] ?? false);
        $selectedMinds = $this->minds->resolveMindsForUser($user, $mindIds, $includePlatform);

        $kbCreditsSpent = 0;
        $citations      = [];
        $kbContext      = '';
        if ($selectedMinds) {
            try {
                $retrieved = $this->minds->retrieveContext($user, $selectedMinds, $brief);
                $kbContext      = $retrieved['context'];
                $citations      = $retrieved['citations'];
                $kbCreditsSpent = (int) $retrieved['credits_spent'];
            } catch (InsufficientAiCreditsException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Persona Mind retrieval failed: ' . $e->getMessage());
                // Fall through with no context rather than failing the
                // whole generation — Persona still works without KB.
            }
        }

        $systemPrompt = "You are Persona, a brand strategist. Given a short brief, "
            . "output a markdown persona profile with these sections:\n"
            . "**Snapshot** (2-3 sentences)\n"
            . "**Traits** (5 bullet adjectives)\n"
            . "**Voice & tone** (3 short rules)\n"
            . "**Avoid** (3 short rules).\n"
            . "Keep it under 200 words.";
        if ($kbContext !== '') {
            $systemPrompt .= "\n\nGround the persona in the Knowledge Base context below when it is "
                . "relevant. Prefer concrete details from the context (terms, products, audiences) "
                . "over generic phrasing. Do not invent facts that are not in the context.\n\n"
                . "Knowledge Base context:\n" . $kbContext;
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $brief],
        ];

        try {
            $out = $this->ai->chat($request->user(), AiEngineSettings::featureModel('persona'), $messages, [
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

        // Echo back the selection so the form repaints with the same
        // checkboxes / multi-select state on the result page.
        $data['mind_ids']         = array_map('intval', $mindIds);
        $data['include_platform'] = $includePlatform;

        // Persist the latest result on the user's session so the "Save"
        // action can read it server-side instead of trusting hidden inputs.
        $request->session()->put('ai.persona.input', $data);
        $request->session()->put('ai.persona.result', [
            'content'        => $out['content'],
            'credits_spent'  => (int) $out['credits_spent'] + $kbCreditsSpent,
            'model'          => $out['model'],
            'citations'      => $citations,
            'minds_used'     => array_map(
                fn(AiMind $m) => ['id' => (int) $m->id, 'name' => (string) $m->name, 'is_platform' => $m->isPlatform()],
                $selectedMinds,
            ),
        ]);
        return redirect()->route('user.ai.persona.show');
    }

    public function save(Request $request)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $input  = $request->session()->get('ai.persona.input');
        $result = $request->session()->get('ai.persona.result');
        if (!$input || !$result || empty($result['content'])) {
            return redirect()->route('user.ai.persona.show')
                ->with('error', 'Generate a persona before saving it.');
        }

        AiPersona::create([
            'user_id'  => $request->user()->id,
            'name'     => $data['name'],
            'audience' => $input['audience'] ?? '',
            'goals'    => $input['goals'] ?? null,
            'tone'     => $input['tone'] ?? null,
            'content'  => $result['content'],
            'model'    => $result['model'] ?? null,
        ]);

        return redirect()->route('user.ai.persona.show')
            ->with('status', 'Persona saved to your library.');
    }

    public function update(Request $request, AiPersona $persona)
    {
        $this->ensureEnabled();
        abort_if($persona->user_id !== $request->user()->id, 404);
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);
        $persona->update($data);

        return redirect()->route('user.ai.persona.show')
            ->with('status', 'Persona renamed.');
    }

    public function destroy(Request $request, AiPersona $persona)
    {
        $this->ensureEnabled();
        abort_if($persona->user_id !== $request->user()->id, 404);
        $persona->delete();

        return redirect()->route('user.ai.persona.show')
            ->with('status', 'Persona deleted.');
    }

    /** @return \Illuminate\Support\Collection<int,AiMind> */
    protected function userMinds($user)
    {
        return AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function platformMind(): ?AiMind
    {
        return AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
