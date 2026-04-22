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

        $embedModel = AiMindSettings::embeddingModel();
        $chatModel  = AiMindSettings::chatModel();

        // Embed the question on the asker's account so they pay for
        // their own queries, even when querying the platform Mind.
        // Attribute spend to the *focused* (first) Mind so per-Mind
        // analytics can break questions out from ingestion.
        $focusedMind = $minds[0];
        $queryMeta = [
            'kind'    => 'query',
            'mind_id' => (int) $focusedMind->id,
        ];
        $emb = $this->openai->embed($user, $embedModel, [$question], [
            'feature'    => 'mind',
            'related_id' => (int) $focusedMind->id,
            'reason'     => 'Mind query',
            'meta'       => $queryMeta,
        ]);
        $queryVec = $emb['vectors'][0] ?? [];
        $creditsSpent = (int) ($emb['credits_spent'] ?? 0);

        // Pull every embedded chunk in the selected minds and rank by
        // cosine similarity. v1 keeps similarity in PHP — fine for the
        // chunk volumes the per-source cap allows. A future revision
        // can swap in pgvector without changing the read API.
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

        // Live feature snapshots (per-user, per-mind) — never embedded,
        // always recomputed at query time.
        $snapshots = [];
        foreach ($minds as $mind) {
            // The platform mind's owner is null; feature snapshots only
            // make sense against the asking user's account.
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

        // Assemble the prompt. Keep total context under MAX_CONTEXT_CHARS.
        $contextParts = [];
        $citations    = [];
        $used         = 0;
        foreach ($top as $row) {
            /** @var AiMindChunk $chunk */
            $chunk = $row['c'];
            $piece = "---\n" . $chunk->content;
            if ($used + mb_strlen($piece) > self::MAX_CONTEXT_CHARS) break;
            $contextParts[] = $piece;
            $used += mb_strlen($piece);
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
        foreach ($snapshots as $snap) {
            $piece = "---\n[Live: {$snap['label']}]\n" . $snap['text'];
            if ($used + mb_strlen($piece) > self::MAX_CONTEXT_CHARS) break;
            $contextParts[] = $piece;
            $used += mb_strlen($piece);
        }
        $context = implode("\n", $contextParts);

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
            'citations'         => $citations,
            'credits_spent'     => $creditsSpent + (int) ($chat['credits_spent'] ?? 0),
            'tokens_in'         => (int) ($chat['tokens_in'] ?? 0),
            'tokens_out'        => (int) ($chat['tokens_out'] ?? 0),
            'model'             => $chatModel,
            'feature_snapshots' => $snapshots,
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
