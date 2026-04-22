<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\User;

/**
 * Read side of the Mind: embed a question, find the closest chunks
 * across one or more Minds, splice in any selected feature snapshots,
 * and ask the chat model to answer using only that context.
 *
 * Returns the answer plus the cited sources so the UI can show users
 * which knowledge base entries informed each reply.
 *
 * Two entry points:
 *   ask()             — full Q&A flow (used by the Mind test panel).
 *   retrieveContext() — embeds a query and returns formatted context +
 *                       citations, with no chat call. Used by Persona /
 *                       Coach so they can ground their generations in
 *                       the user's selected Minds without paying for an
 *                       extra LLM round-trip.
 */
class AiMindQueryService
{
    public const MAX_CONTEXT_CHARS = 8000;
    public const TOP_K             = 6;

    public function __construct(
        protected OpenAiService $openai,
        protected AiMindFeatureAdapter $features,
    ) {}

    /**
     * Resolve a list of mind ids the given user is allowed to query.
     * Drops disabled minds, ids the user does not own, and (when
     * $includePlatform is false) the platform default mind. Always
     * returns a fresh, de-duplicated array of AiMind models.
     *
     * @param array<int,int|string> $mindIds
     * @return array<int,AiMind>
     */
    public function resolveMindsForUser(User $user, array $mindIds, bool $includePlatform): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $mindIds))));
        if (!$ids && !$includePlatform) {
            return [];
        }

        $query = AiMind::query()->where('is_disabled', false);
        $query->where(function ($q) use ($ids, $includePlatform, $user) {
            if ($ids) {
                $q->orWhere(function ($qq) use ($ids, $user) {
                    $qq->whereIn('id', $ids)->where('user_id', $user->id);
                });
            }
            if ($includePlatform) {
                $q->orWhere(function ($qq) {
                    $qq->whereNull('user_id')->where('is_default', true);
                });
            }
        });

        return $query->get()->all();
    }

    /**
     * Retrieve the top matching chunks (and live feature snapshots) for
     * a free-text query across the given minds. Returns the assembled
     * context block plus structured citations and the credit cost of
     * the embedding call. No chat completion is performed.
     *
     * @param array<int,AiMind> $minds
     * @return array{
     *   context:string,
     *   citations:array<int,array{id:int,title:string,type:string,mind_id:int,score:float}>,
     *   feature_snapshots:array<int,array{key:string,label:string,text:string}>,
     *   mind_stats:array<int,array{chunks_used:int,top_score:float}>,
     *   credits_spent:int,
     * }
     */
    public function retrieveContext(User $user, array $minds, string $query): array
    {
        $query = trim($query);
        $minds = array_values(array_filter($minds, fn($m) => $m && !$m->is_disabled));
        if (!$minds || $query === '') {
            return [
                'context'           => '',
                'citations'         => [],
                'feature_snapshots' => [],
                'mind_stats'        => [],
                'credits_spent'     => 0,
            ];
        }

        $embedModel = AiMindSettings::embeddingModel();
        $focusedMind = $minds[0];
        $queryMeta = [
            'kind'    => 'query',
            'mind_id' => (int) $focusedMind->id,
        ];
        $emb = $this->openai->embed($user, $embedModel, [$query], [
            'feature'    => 'mind',
            'related_id' => (int) $focusedMind->id,
            'reason'     => 'Mind context retrieval',
            'meta'       => $queryMeta,
        ]);
        $queryVec = $emb['vectors'][0] ?? [];
        $creditsSpent = (int) ($emb['credits_spent'] ?? 0);

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

        $snapshots = [];
        foreach ($minds as $mind) {
            $owner = $mind->user_id ? $mind->user : $user;
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

        $contextParts = [];
        $citations    = [];
        $mindStats    = [];
        $used         = 0;
        foreach ($top as $row) {
            /** @var AiMindChunk $chunk */
            $chunk = $row['c'];
            $piece = "---\n" . $chunk->content;
            if ($used + mb_strlen($piece) > self::MAX_CONTEXT_CHARS) break;
            $contextParts[] = $piece;
            $used += mb_strlen($piece);
            $score = round((float) $row['score'], 4);
            $mid = (int) $chunk->mind_id;
            if (!isset($mindStats[$mid])) {
                $mindStats[$mid] = ['chunks_used' => 0, 'top_score' => 0.0];
            }
            $mindStats[$mid]['chunks_used']++;
            if ($score > $mindStats[$mid]['top_score']) {
                $mindStats[$mid]['top_score'] = $score;
            }
            $src = AiMindSource::find($chunk->source_id);
            if ($src) {
                $citations[] = [
                    'id'      => (int) $src->id,
                    'title'   => (string) $src->title,
                    'type'    => (string) $src->type,
                    'mind_id' => (int) $src->mind_id,
                    'score'   => $score,
                ];
            }
        }
        foreach ($snapshots as $snap) {
            $piece = "---\n[Live: {$snap['label']}]\n" . $snap['text'];
            if ($used + mb_strlen($piece) > self::MAX_CONTEXT_CHARS) break;
            $contextParts[] = $piece;
            $used += mb_strlen($piece);
        }

        return [
            'context'           => implode("\n", $contextParts),
            'citations'         => $citations,
            'feature_snapshots' => $snapshots,
            'mind_stats'        => $mindStats,
            'credits_spent'     => $creditsSpent,
        ];
    }

    /**
     * @param array<int,AiMind> $minds Knowledge bases to search.
     * @return array{
     *   answer:string,
     *   citations:array<int,array{id:int,title:string,type:string,mind_id:int,score:float}>,
     *   credits_spent:int,
     *   tokens_in:int,
     *   tokens_out:int,
     *   model:string,
     *   feature_snapshots:array<int,array{key:string,label:string,text:string}>,
     * }
     */
    public function ask(User $user, array $minds, string $question): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new \InvalidArgumentException('Question is required.');
        }
        $minds = array_values(array_filter($minds, fn($m) => $m && !$m->is_disabled));
        if (!$minds) {
            throw new \RuntimeException('No active Mind selected for this query.');
        }

        $focusedMind = $minds[0];
        $queryMeta = [
            'kind'    => 'query',
            'mind_id' => (int) $focusedMind->id,
        ];
        $chatModel = AiMindSettings::chatModel();

        $retrieved = $this->retrieveContext($user, $minds, $question);
        $context = $retrieved['context'];

        $messages = [
            ['role' => 'system', 'content' =>
                "You are a helpful assistant that answers strictly from the Knowledge Base context. "
                . "If the context does not contain the answer, say so honestly and suggest where the user could look. "
                . "Be concise. Quote facts from context when relevant; never invent URLs or numbers."],
            ['role' => 'user', 'content' =>
                "Knowledge Base context:\n{$context}\n\nQuestion: {$question}"],
        ];

        $chat = $this->openai->chat($user, $chatModel, $messages, [
            'feature'    => 'mind',
            'related_id' => (int) $focusedMind->id,
            'reason'     => 'Mind query',
            'meta'       => $queryMeta,
            'temperature' => 0.2,
            'max_tokens'  => 700,
        ]);

        return [
            'answer'            => $chat['content'],
            'citations'         => $retrieved['citations'],
            'credits_spent'     => $retrieved['credits_spent'] + (int) ($chat['credits_spent'] ?? 0),
            'tokens_in'         => (int) ($chat['tokens_in'] ?? 0),
            'tokens_out'        => (int) ($chat['tokens_out'] ?? 0),
            'model'             => $chatModel,
            'feature_snapshots' => $retrieved['feature_snapshots'],
        ];
    }

    /** Cosine similarity for two float vectors. Safe on empty/length-mismatch. */
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
