<?php

namespace App\Services\AI;

use App\Models\User;

/**
 * Single source of truth for the "what will this cost me?" coin estimates
 * shown next to creator-facing AI triggers. Every estimate mirrors the real
 * charge path — same model rates, same per-plan multiplier — so the number a
 * creator previews lines up with what their wallet is actually debited.
 *
 * Features that already own an accurate, input-specific estimate endpoint
 * (biolink builder, brand kit, marketing strategist, resume tailor) keep using
 * theirs; this class covers everything else with a conservative "Up to X"
 * worst case (input tokens + a fixed per-feature prompt overhead + the
 * feature's output ceiling) and the bounded "fixed" costs (QR art, a voice
 * turn, an embedding per ~1,000 words).
 */
class AiCostEstimator
{
    /**
     * Per-feature chat worst case: [model feature key, max output tokens,
     * fixed prompt overhead tokens (system prompt + grounding the client
     * can't cheaply reconstruct)].
     */
    private const CHAT = [
        'persona'             => ['persona', 500, 350],
        'ask_coach'           => ['ask_coach', 600, 1400],
        'resume_import'       => ['resume_import', 1200, 400],
        'resume_cover_letter' => ['resume_cover_letter', 1400, 700],
    ];

    public function __construct(private OpenAiService $openai)
    {
    }

    /**
     * @return array{coins:int, mode:string} mode is 'live' (input drives the
     *         number) or 'fixed' (bounded "up to" cost).
     */
    public function estimate(User $user, string $feature, string $text): array
    {
        if (isset(self::CHAT[$feature])) {
            [$modelKey, $maxOut, $overhead] = self::CHAT[$feature];
            $coins = $this->openai->estimateChatCoinsForText(
                AiEngineSettings::featureModel($modelKey),
                $text,
                $overhead,
                $maxOut,
                $user,
            );
            return ['coins' => $coins, 'mode' => 'live'];
        }

        switch ($feature) {
            case 'card_scan':
                // Vision extraction: the uploaded card/brochure dominates the
                // prompt, so quote a worst case from a representative image
                // payload plus the structured-output ceiling.
                return [
                    'coins' => $this->openai->estimateChatCoinsForText(
                        AiEngineSettings::featureModel('card_scan'),
                        '',
                        1200,
                        1500,
                        $user,
                    ),
                    'mode' => 'fixed',
                ];

            case 'qr_art':
                return ['coins' => $this->qrArtCoins($user), 'mode' => 'fixed'];

            case 'voice':
                return ['coins' => $this->voiceTurnCoins($user), 'mode' => 'fixed'];

            case 'minds':
                return ['coins' => $this->mindsPerThousandWords($user), 'mode' => 'fixed'];
        }

        return ['coins' => 0, 'mode' => 'fixed'];
    }

    /**
     * Approximate "coins per day" for an AI chat widget (companion), derived
     * from this month's usage with a worst-case ceiling implied by the hard
     * monthly cap. free_turns_per_month turns bill at ~0, so they're already
     * excluded from the recorded credit spend we average over.
     *
     * @param  array{turns?:int, credits?:int}  $usage
     * @return array{per_day:int, avg_per_turn:float, ceiling_per_day:?int, has_cap:bool, free_turns:int, based_on_usage:bool}
     */
    public function companionPerDay(User $owner, array $usage, int $freeTurns, int $hardCap, int $maxTokens): array
    {
        $turns   = max(0, (int) ($usage['turns'] ?? 0));
        $credits = max(0, (int) ($usage['credits'] ?? 0));
        $day     = max(1, (int) now()->day);

        $perDay = (int) round($credits / $day);

        $avgPerTurn = $turns > 0
            ? $credits / $turns
            : (float) $this->openai->estimateChatCoinsForText(
                AiEngineSettings::featureModel('companion'),
                str_repeat('x', 400),
                600,
                max(1, $maxTokens ?: 700),
                $owner,
            );

        $hasCap        = $hardCap > 0;
        $ceilingPerDay = null;
        if ($hasCap) {
            $billable      = max(0, $hardCap - max(0, $freeTurns));
            $ceilingPerDay = (int) ceil($billable * $avgPerTurn / 30);
        }

        return [
            'per_day'         => $perDay,
            'avg_per_turn'    => round($avgPerTurn, 1),
            'ceiling_per_day' => $ceilingPerDay,
            'has_cap'         => $hasCap,
            'free_turns'      => max(0, $freeTurns),
            'based_on_usage'  => $turns > 0,
        ];
    }

    private function qrArtCoins(User $user): int
    {
        $base = AiEngineSettings::qrArtCoinsPerGeneration();
        $mult = AiPlanAccess::coinMultiplier($user, 'replicate');
        return max(1, (int) ceil($base * $mult));
    }

    private function voiceTurnCoins(User $user): int
    {
        if (!AiEngineSettings::isEnabled()) {
            return 0;
        }
        $openaiMult    = AiPlanAccess::coinMultiplier($user, 'openai');
        $elevenLabsMlt = AiPlanAccess::coinMultiplier($user, 'elevenlabs');

        // STT: ~1 minute of speech. Reasoning: a short grounded turn.
        // TTS: a ~600-char spoken reply.
        $stt    = (int) ceil(1.0 * AiEngineSettings::voiceSttCoinsPerMinute() * $openaiMult);
        $reason = $this->openai->estimateChatCoinsForText(
            AiEngineSettings::featureModel('companion'),
            str_repeat('x', 600),
            600,
            350,
            $user,
        );
        $tts = (int) ceil(600 / 1000 * AiEngineSettings::voiceTtsCoinsPer1kChars() * $elevenLabsMlt);

        return max(1, $stt + $reason + $tts);
    }

    private function mindsPerThousandWords(User $user): int
    {
        return max(0, $this->openai->estimateEmbeddingCoinsForText(
            AiMindSettings::embeddingModel(),
            str_repeat('word ', 1000),
            $user,
        ));
    }
}
