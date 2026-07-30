<?php

namespace App\Services\AI;

use App\Modules\User\Models\CompetitorTeardown;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Competitor Biolink Teardown (Task #3532).
 *
 * Flow:
 *   1. {@see CompetitorPageFetcher} fetches + extracts the competitor's
 *      page server-side. Failures here (bad URL, unreachable host,
 *      non-HTML response…) throw BEFORE any AI credits are touched.
 *   2. The extracted signals are sent to {@see OpenAiService::chat} with
 *      `feature='competitor_teardown'` so credit metering, model gating
 *      and the wallet audit trail flow through the same chokepoint as
 *      every other AI feature.
 *   3. The parsed JSON is normalised into a flat scored teardown
 *      (strengths / weaknesses / missing elements / CTA quality).
 *
 * On failure mid-analysis we mark the row failed and refund any partial
 * credit charge, mirroring {@see CardBrochureExtractionService}.
 *
 * `buildBetterVersion()` hands a completed teardown off to the existing
 * {@see AiBiolinkBuilderService} — it creates a fresh biolink shell and
 * asks the builder to generate a page description built from the
 * teardown's findings, reusing the builder's own credit charge/refund
 * and safe block-subset constraints (nothing new to charge/gate here).
 */
class CompetitorTeardownService
{
    public const FEATURE = 'competitor_teardown';

    private const MAX_OUTPUT_TOKENS = 1400;

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
        protected CompetitorPageFetcher $fetcher,
        protected AiBiolinkBuilderService $builder,
    ) {}

    /**
     * @throws RuntimeException for caller-fixable problems (bad URL,
     *         unreachable page, unparsable AI response).
     * @throws InsufficientCoinsForAiException when the wallet can't
     *         cover the worst-case chat call.
     */
    public function analyze(User $owner, User $actor, string $url): CompetitorTeardown
    {
        // Fetch + extract FIRST — no AI spend on a bad URL.
        $extracted = $this->fetcher->fetchAndExtract($url);

        $teardown = CompetitorTeardown::create([
            'user_id'       => $owner->id,
            'actor_user_id' => $actor->id,
            'competitor_url'=> $extracted['final_url'] ?: $url,
            'status'        => 'processing',
            'extracted'     => $extracted,
        ]);

        $model    = AiEngineSettings::featureModel(self::FEATURE, $owner);
        $messages = $this->buildMessages($extracted);

        try {
            $result = $this->openai->chat($owner, $model, $messages, [
                'feature'          => self::FEATURE,
                'related_id'       => $teardown->id,
                'response_format'  => ['type' => 'json_object'],
                'temperature'      => 0.3,
                'max_tokens'       => self::MAX_OUTPUT_TOKENS,
                'reason'           => 'Competitor biolink teardown',
                'meta'             => ['url' => mb_substr($extracted['final_url'] ?: $url, 0, 200)],
            ]);
        } catch (InsufficientCoinsForAiException $e) {
            // Nothing spent yet — mark failed and let the controller
            // route the user to the top-up screen.
            $teardown->forceFill([
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 480),
            ])->save();
            throw $e;
        } catch (\Throwable $e) {
            $teardown->forceFill([
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 480),
            ])->save();
            throw $e;
        }

        // Persist the spend BEFORE any further parsing so a downstream
        // failure can still refund the exact amount charged.
        $creditsSpent = (int) ($result['credits_spent'] ?? 0);
        if ($creditsSpent > 0) {
            $teardown->forceFill(['credits_spent' => $creditsSpent])->save();
        }

        try {
            $parsed = json_decode((string) ($result['content'] ?? ''), true);
            if (!is_array($parsed)) {
                throw new RuntimeException('The assistant returned an unexpected response. Please try again.');
            }

            $teardown->forceFill([
                'status'   => 'completed',
                'analysis' => $this->normalizeAnalysis($parsed),
            ])->save();

            return $teardown->fresh();
        } catch (\Throwable $e) {
            $teardown->forceFill([
                'status' => 'failed',
                'error'  => mb_substr($e->getMessage(), 0, 480),
            ])->save();

            if ($creditsSpent > 0) {
                try {
                    $this->credits->refund($owner, $creditsSpent, [
                        'feature'         => self::FEATURE,
                        'related_id'      => $teardown->id,
                        'reason'          => 'Competitor teardown failed: refunded',
                        'idempotency_key' => "competitor_teardown_refund:{$teardown->id}",
                    ]);
                    $teardown->forceFill(['credits_spent' => 0])->save();
                } catch (\Throwable $r) {
                    Log::warning('competitor_teardown refund failed', ['id' => $teardown->id, 'err' => $r->getMessage()]);
                }
            }

            throw $e;
        }
    }

    /**
     * Turn a completed teardown into a new biolink via the existing AI
     * builder. Creates a fresh biolink shell first (mirrors
     * WhatsAppAgentTools/WizardAiDraftService), then hands it to
     * {@see AiBiolinkBuilderService::generate()}. The builder owns its
     * own credit charge/refund and safe block-subset constraints — we
     * add none of our own here, only the prompt description synthesised
     * from the teardown's findings.
     *
     * @throws RuntimeException|InsufficientCoinsForAiException bubbled
     *         from the builder; the shell link is deleted on failure so
     *         no empty page is left behind.
     */
    public function buildBetterVersion(User $owner, User $actor, CompetitorTeardown $teardown): Link
    {
        if ($teardown->status !== 'completed' || !is_array($teardown->analysis)) {
            throw new RuntimeException("This teardown isn't ready yet.");
        }

        $host  = parse_url((string) $teardown->competitor_url, PHP_URL_HOST) ?: 'competitor';
        $title = 'Better than ' . $host;

        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => mb_substr($title, 0, 191),
            'is_active' => true,
        ]);

        try {
            $this->builder->generate(
                $owner,
                $link,
                $this->describeForBuilder($teardown),
                [],
                [],
                [],
                '',
                true,
                '',
            );
        } catch (\Throwable $e) {
            // The builder auto-refunds its own credits on failure; drop
            // the empty shell link so we don't leave debris behind.
            $link->delete();
            throw $e;
        }

        $teardown->forceFill(['built_link_id' => $link->id])->save();

        return $link->fresh();
    }

    /**
     * @param array<string,mixed> $extracted
     * @return list<array{role:string,content:string}>
     */
    protected function buildMessages(array $extracted): array
    {
        $payload = json_encode([
            'url'               => $extracted['final_url'] ?? '',
            'title'             => $extracted['title'] ?? '',
            'meta_description'  => $extracted['meta_description'] ?? '',
            'headings_h1'       => $extracted['h1'] ?? [],
            'headings_h2'       => $extracted['h2'] ?? [],
            'cta_texts'         => $extracted['cta_texts'] ?? [],
            'link_count'        => $extracted['link_count'] ?? 0,
            'image_count'       => $extracted['image_count'] ?? 0,
            'form_count'        => $extracted['form_count'] ?? 0,
            'has_email_capture' => $extracted['has_email_capture'] ?? false,
            'has_social_links'  => $extracted['has_social_links'] ?? false,
            'text_excerpt'      => $extracted['text_excerpt'] ?? '',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        return [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => "Analyze this page's link-in-bio / landing-page effectiveness:\n\n" . $payload],
        ];
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert conversion-rate + link-in-bio design critic. You are
given structured signals scraped from a competitor's public web page
(title, headings, button/link CTA copy, counts, a text excerpt). Score
the page's effectiveness as a "link in bio" / mini landing page and
emit ONLY a single JSON object matching this schema — no prose, no
markdown fences:

{
  "overall_score": number,        // 0-100, overall effectiveness
  "summary": string,               // 1-2 sentence verdict
  "strengths": [string],           // up to 6, concrete and specific
  "weaknesses": [string],          // up to 6, concrete and specific
  "missing_elements": [string],    // up to 6 things a strong link-in-bio page would have but this lacks (e.g. "no email capture", "no clear single primary CTA", "no social proof")
  "cta": {
    "present": boolean,
    "quality_score": number,       // 0-100
    "feedback": string             // 1-2 sentences on the CTA(s)
  },
  "recommendations": [string]      // up to 6 concrete, actionable fixes, ordered by impact
}

Rules:
- Base every judgement only on the provided signals; never invent facts about the brand.
- Be specific and actionable, not generic ("weak CTA" is bad; "the CTA text 'Learn More' doesn't tell visitors what happens next — swap for a specific action like 'Book a 15-min call'" is good).
- If cta_texts is empty, treat cta.present as false and cta.quality_score as low.
- Keep every string under 200 characters.
- Output ONLY the JSON object.
PROMPT;
    }

    /**
     * Clamp/cap the parsed analysis into the shape the view expects.
     *
     * @param array<string,mixed> $parsed
     * @return array<string,mixed>
     */
    protected function normalizeAnalysis(array $parsed): array
    {
        $clampScore = static function ($v): int {
            $n = is_numeric($v) ? (int) round((float) $v) : 0;
            return max(0, min(100, $n));
        };
        $strList = static function ($v, int $max): array {
            if (!is_array($v)) return [];
            $out = [];
            foreach ($v as $item) {
                if (!is_string($item)) continue;
                $t = trim($item);
                if ($t === '') continue;
                $out[] = mb_substr($t, 0, 220);
                if (count($out) >= $max) break;
            }
            return $out;
        };

        $cta = is_array($parsed['cta'] ?? null) ? $parsed['cta'] : [];

        return [
            'overall_score'   => $clampScore($parsed['overall_score'] ?? 0),
            'summary'         => mb_substr(trim((string) ($parsed['summary'] ?? '')), 0, 400),
            'strengths'       => $strList($parsed['strengths'] ?? [], 6),
            'weaknesses'      => $strList($parsed['weaknesses'] ?? [], 6),
            'missing_elements'=> $strList($parsed['missing_elements'] ?? [], 6),
            'cta'             => [
                'present'       => (bool) ($cta['present'] ?? false),
                'quality_score' => $clampScore($cta['quality_score'] ?? 0),
                'feedback'      => mb_substr(trim((string) ($cta['feedback'] ?? '')), 0, 300),
            ],
            'recommendations' => $strList($parsed['recommendations'] ?? [], 6),
        ];
    }

    /** Compose the AI biolink builder's free-text description from a teardown's findings. */
    protected function describeForBuilder(CompetitorTeardown $teardown): string
    {
        $analysis = is_array($teardown->analysis) ? $teardown->analysis : [];
        $extracted = is_array($teardown->extracted) ? $teardown->extracted : [];

        $lines   = [];
        $lines[] = 'Build a link-in-bio page that beats a competitor page we analysed (' . ($teardown->competitor_url ?: 'the competitor URL') . ').';
        if (!empty($extracted['title'])) {
            $lines[] = "Competitor's page title: " . $extracted['title'];
        }
        if (!empty($analysis['summary'])) {
            $lines[] = 'Teardown verdict: ' . $analysis['summary'];
        }
        if (!empty($analysis['strengths'])) {
            $lines[] = 'Keep/improve on what already works there: ' . implode('; ', $analysis['strengths']);
        }
        if (!empty($analysis['weaknesses'])) {
            $lines[] = 'Avoid these weaknesses we found on their page: ' . implode('; ', $analysis['weaknesses']);
        }
        if (!empty($analysis['missing_elements'])) {
            $lines[] = 'Make sure to include what they are missing: ' . implode('; ', $analysis['missing_elements']);
        }
        if (!empty($analysis['recommendations'])) {
            $lines[] = 'Apply these improvements: ' . implode('; ', $analysis['recommendations']);
        }
        $lines[] = 'Give this new page a single clear, compelling primary call-to-action button.';

        return mb_substr(implode("\n", $lines), 0, 3800);
    }
}
