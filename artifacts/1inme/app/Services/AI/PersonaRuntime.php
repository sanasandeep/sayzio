<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\User;

/**
 * "Run a turn" service for an AI Persona.
 *
 * Takes a Persona + chat history + the visitor's new message and:
 *   1. Resolves the active Mind set (attached + optional default).
 *   2. Embeds the user message and pulls the top-K most relevant
 *      chunks across those Minds (vector cosine in PHP, same as the
 *      Mind test panel — swap for pgvector later without changing
 *      this API).
 *   3. Splices in feature snapshots (live per-user data) for any
 *      `feature` sources attached to those Minds.
 *   4. Assembles the system prompt from persona config (role + tone +
 *      style + allowed actions + fallback rule + cited sources hint).
 *   5. Calls OpenAI through OpenAiService, which meters credits.
 *
 * Returns the answer plus citations and per-turn token / credit usage
 * so the test panel — and later widgets / Coach — can show users
 * exactly what each turn cost and which sources informed it.
 */
class PersonaRuntime
{
    /** Cap total injected context (chunks + snapshots) per turn. */
    public const MAX_CONTEXT_CHARS = 8000;
    public const TOP_K             = 6;

    public function __construct(
        protected OpenAiService $openai,
        protected AiMindFeatureAdapter $features,
    ) {}

    /**
     * @param array<int,array{role:string,content:string}> $history
     *        Prior turns (excluding the new user message). The
     *        caller is responsible for trimming history to a sensible
     *        window — this service does not persist anything.
     * @return array{
     *   answer:string, citations:array, credits_spent:int,
     *   tokens_in:int, tokens_out:int, model:string,
     *   feature_snapshots:array, system_prompt:string,
     * }
     */
    public function turn(User $user, AiPersonaAgent $persona, array $history, string $message): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Message is required.');
        }
        if ($persona->is_disabled) {
            throw new \RuntimeException('This Persona is disabled.');
        }

        // Resolve the Mind set: explicitly attached + (opt-in) the
        // platform default. Skip disabled Minds — querying them would
        // fail the chunk lookup anyway and silently dropping is the
        // friendlier behaviour for visitors.
        $minds = $persona->minds()->where('is_disabled', false)->get()->all();
        if ($persona->use_default_mind) {
            $defaults = AiMind::whereNull('user_id')
                ->where('is_default', true)
                ->where('is_disabled', false)
                ->get()->all();
            $minds = array_merge($minds, $defaults);
        }

        $citations = [];
        $context   = '';
        $embedCredits = 0;

        // Only run the embedding + retrieval when we actually have
        // something to retrieve from — saves a credit charge when the
        // persona is configured to be a pure "voice" with no Minds.
        if (!empty($minds)) {
            $embedModel = AiMindSettings::embeddingModel();
            $emb = $this->openai->embed($user, $embedModel, [$message], [
                'feature'    => 'persona',
                'related_id' => $persona->id,
                'reason'     => "Persona #{$persona->id} retrieval embed",
            ]);
            $embedCredits = (int) ($emb['credits_spent'] ?? 0);
            $queryVec = $emb['vectors'][0] ?? [];

            $candidates = AiMindChunk::query()
                ->whereIn('mind_id', collect($minds)->pluck('id'))
                ->limit(2000)
                ->get(['id','mind_id','source_id','content','tokens','embedding','ord']);
            $scored = [];
            foreach ($candidates as $c) {
                $vec = is_array($c->embedding) ? $c->embedding : [];
                $score = $vec ? $this->cosine($queryVec, $vec) : 0.0;
                $scored[] = ['c' => $c, 'score' => $score];
            }
            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $top = array_slice($scored, 0, self::TOP_K);

            $parts = [];
            $used  = 0;
            foreach ($top as $row) {
                $chunk = $row['c'];
                $piece = "---\n" . $chunk->content;
                if ($used + mb_strlen($piece) > self::MAX_CONTEXT_CHARS) break;
                $parts[] = $piece;
                $used   += mb_strlen($piece);
                $src = AiMindSource::find($chunk->source_id);
                if ($src) {
                    $citations[] = [
                        'id'      => (int) $src->id,
                        'title'   => (string) $src->title,
                        'type'    => (string) $src->type,
                        'mind_id' => (int) $src->mind_id,
                        'score'   => round((float) $row['score'], 4),
                    ];
                }
            }

            // Live feature snapshots — never embedded, always fresh.
            // Snapshots only make sense for the persona owner: never
            // leak the owner's data when the visitor is someone else.
            $snapshots = [];
            foreach ($minds as $mind) {
                $owner = $mind->user_id ? $mind->user : $persona->user;
                if (!$owner) continue;
                $featureSources = $mind->sources()
                    ->where('type', AiMindSource::TYPE_FEATURE)
                    ->get(['id','feature_key']);
                foreach ($featureSources as $fs) {
                    $key = (string) $fs->feature_key;
                    if (!AiMindFeatureAdapter::isFeature($key)) continue;
                    $text = $this->features->snapshot($owner, $key);
                    if ($text === '') continue;
                    $snapshots[] = [
                        'key'   => $key,
                        'label' => AiMindFeatureAdapter::label($key),
                        'text'  => $text,
                    ];
                }
            }
            foreach ($snapshots as $snap) {
                $piece = "---\n[Live: {$snap['label']}]\n" . $snap['text'];
                if ($used + mb_strlen($piece) > self::MAX_CONTEXT_CHARS) break;
                $parts[] = $piece;
                $used   += mb_strlen($piece);
            }
            $context = implode("\n", $parts);
        } else {
            $snapshots = [];
        }

        $systemPrompt = $this->buildSystemPrompt($persona, $context);

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') continue;
            $messages[] = ['role' => $role, 'content' => $content];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $chat = $this->openai->chat($user, $persona->model, $messages, [
            'feature'     => 'persona',
            'related_id'  => $persona->id,
            'reason'      => "Persona #{$persona->id} turn",
            'temperature' => $persona->temperature(),
            'max_tokens'  => (int) $persona->max_tokens,
        ]);

        // Touch last_used_at so admins can see which personas are
        // actually getting traffic without scanning the credit ledger.
        $persona->forceFill(['last_used_at' => now()])->save();

        return [
            'answer'            => (string) $chat['content'],
            'citations'         => $citations,
            'credits_spent'     => $embedCredits + (int) ($chat['credits_spent'] ?? 0),
            'tokens_in'         => (int) ($chat['tokens_in'] ?? 0),
            'tokens_out'        => (int) ($chat['tokens_out'] ?? 0),
            'model'             => $persona->model,
            'feature_snapshots' => $snapshots,
            'system_prompt'     => $systemPrompt,
        ];
    }

    protected function buildSystemPrompt(AiPersonaAgent $persona, string $context): string
    {
        $parts = [];
        $parts[] = "You are {$persona->name}.";
        if ($persona->description) {
            $parts[] = "Role: " . $persona->description;
        }
        $parts[] = trim($persona->system_prompt);

        if ($persona->tone_preset) {
            $parts[] = "Tone preset: {$persona->tone_preset}.";
        }
        if ($persona->style_guide) {
            $parts[] = "Style guide:\n" . $persona->style_guide;
        }

        // On-Brand AI (Task #2664): inject the owner's saved Brand Kit voice
        // & tone so the Companion stays on-brand. Opt-out per persona via
        // use_brand_kit (default on — null/legacy rows count as on), and
        // plan-gated behind the legacy-safe `brand_consistency` feature.
        if ($persona->use_brand_kit !== false) {
            $owner = $persona->user;
            if ($owner && AiPlanAccess::featureAllowed($owner, 'brand_consistency')) {
                $kit = BrandKit::defaultFor($owner->id);
                $directives = $kit ? $kit->promptDirectives(false) : '';
                if ($directives !== '') {
                    $parts[] = $directives;
                }
            }
        }

        $langs = $persona->languages ?: [];
        if ($langs) {
            $parts[] = "Respond in the visitor's language. Supported: " . implode(', ', $langs) . '.';
        }

        $actions = (array) ($persona->allowed_actions ?? []);
        $rules = [];
        foreach (AiPersonaAgent::ACTIONS as $key => $label) {
            $on = (bool) ($actions[$key] ?? false);
            // Only mention the rule when the operator turned it on —
            // listing every "may not" wastes context budget.
            if ($on) $rules[] = "- {$label}.";
        }
        if ($rules) {
            $parts[] = "Allowed actions / rules:\n" . implode("\n", $rules);
        }

        $fallback = $persona->fallback_behavior ?: 'clarify';
        $parts[] = match ($fallback) {
            'escalate' => "When you are uncertain or the question is outside the knowledge base, say so honestly and offer to connect the visitor with a human.",
            'refuse'   => "When you are uncertain or the question is outside the knowledge base, refuse politely and explain what you *can* help with.",
            default    => "When you are uncertain, ask a clarifying question rather than guessing.",
        };

        if ($context !== '') {
            $parts[] = "Knowledge Base context (cite from these when relevant; never invent URLs or numbers):\n" . $context;
        } else {
            $parts[] = "No knowledge base context is attached for this turn — answer from general knowledge but flag any specifics you cannot verify.";
        }

        return implode("\n\n", $parts);
    }

    protected function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) return 0.0;
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $av = (float) $a[$i]; $bv = (float) $b[$i];
            $dot += $av * $bv;
            $na  += $av * $av;
            $nb  += $bv * $bv;
        }
        if ($na <= 0 || $nb <= 0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
