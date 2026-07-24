<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared pipeline for the per-link-type "Build with AI" tools (Task #5727).
 *
 * Mirrors the contract proven by {@see \App\Services\Biolink\AiBiolinkBuilderService}
 * (which stays untouched — its behaviour is load-bearing for the Link in Bio
 * flow): the chat call itself charges the wallet via OpenAiService, and if
 * the response can't be parsed or materialized into real content, the exact
 * charged credits are refunded so a failed build never nets a charge.
 *
 * Subclasses supply the AI-credit feature key, the JSON contract prompt, and
 * the materializer that turns the parsed payload into rows for their type.
 */
abstract class AbstractAiTypeBuilderService
{
    /** Hard input caps shared by every type builder. */
    public const MAX_DESCRIPTION = 4000;
    public const MAX_LINKS       = 10;
    public const MAX_IMAGES      = 10;

    /** Output budget for the generation call (shared default). */
    public const MAX_OUTPUT_TOKENS = 4000;

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $charger,
    ) {}

    /** AI-credit feature key (must exist in AiFeatureCatalog + AiEngineSettings::FEATURES). */
    abstract public function feature(): string;

    /** The links.type this builder materializes into. */
    abstract public function linkType(): string;

    /** Human label used in charge reasons ("AI Slides builder", ...). */
    abstract public function label(): string;

    /**
     * System prompt describing the strict JSON contract for this type.
     * Must instruct the model to answer with a single JSON object.
     */
    abstract protected function systemPrompt(User $user): string;

    /**
     * Turn the parsed model JSON into persisted rows for $link.
     * Runs inside a DB transaction. Must throw \RuntimeException when the
     * payload yields no usable content (triggers the auto-refund).
     *
     * @param  array $parsed  decoded model JSON
     * @param  array $links   caller-supplied URLs (already cleaned)
     * @param  array $images  caller-supplied image URLs (already cleaned)
     * @return array          summary meta for the UI (e.g. counts)
     */
    abstract protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array;

    /** Whether the intake should offer an image list for this type. */
    public function supportsImages(): bool
    {
        return true;
    }

    /** Whether the intake should offer a URL list for this type. */
    public function supportsLinks(): bool
    {
        return true;
    }

    /** Coin estimate for the given inputs (same model the generation will use). */
    public function estimateCredits(User $user, string $description, array $links, array $images): int
    {
        $messages = $this->buildMessages($user, $description, $this->cleanUrls($links), $this->cleanImageUrls($images));
        $model    = AiEngineSettings::featureModel($this->feature());

        return $this->openai->estimateChatCoins($model, $messages, static::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the paid generation and materialize the result.
     *
     * @return array{credits_spent:int, summary:array}
     * @throws \RuntimeException when the response can't be turned into content
     *         (the charge is refunded first).
     */
    public function generate(User $user, Link $link, string $description, array $links, array $images): array
    {
        $links  = $this->cleanUrls($links);
        $images = $this->supportsImages() ? $this->cleanImageUrls($images) : [];

        $messages = $this->buildMessages($user, $description, $links, $images);
        $model    = AiEngineSettings::featureModel($this->feature());

        $response = $this->openai->chat($user, $model, $messages, [
            'max_tokens'      => static::MAX_OUTPUT_TOKENS,
            'temperature'     => 0.7,
            'response_format' => ['type' => 'json_object'],
            'feature'         => $this->feature(),
            'related_id'      => $link->id,
            'reason'          => $this->label(),
        ]);

        $creditsSpent = (int) ($response['credits_spent'] ?? 0);

        try {
            $parsed = $this->parseJson((string) ($response['content'] ?? ''));

            $summary = DB::transaction(fn () => $this->materialize($user, $link, $parsed, $links, $images));

            return [
                'credits_spent' => $creditsSpent,
                'summary'       => $summary,
            ];
        } catch (\Throwable $e) {
            // Failed build — refund exactly what the chat call charged.
            if ($creditsSpent > 0) {
                try {
                    $this->charger->refund($user, $creditsSpent, [
                        'feature'    => $this->feature(),
                        'related_id' => $link->id,
                        'reason'     => $this->label() . ' failed — auto refund',
                    ]);
                } catch (\Throwable $refundError) {
                    Log::error('AI type builder refund failed', [
                        'feature' => $this->feature(),
                        'link_id' => $link->id,
                        'error'   => $refundError->getMessage(),
                    ]);
                }
            }

            if ($e instanceof \RuntimeException) {
                throw $e;
            }

            Log::warning('AI type builder materialization failed', [
                'feature' => $this->feature(),
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);
            throw new \RuntimeException('The AI response could not be turned into a page. Your coins were refunded — please try again.');
        }
    }

    /** Chat messages: shared framing + subclass contract + the user's brief. */
    protected function buildMessages(User $user, string $description, array $links, array $images): array
    {
        $userParts = [
            "Brief:\n" . mb_substr(trim($description), 0, self::MAX_DESCRIPTION),
        ];

        if ($links) {
            $userParts[] = "URLs supplied by the user (use them where they fit; never invent other URLs):\n- " . implode("\n- ", $links);
        }

        if ($this->supportsImages()) {
            if ($images) {
                $userParts[] = "Image URLs supplied by the user (the ONLY images you may reference, keep each URL EXACTLY as given):\n- " . implode("\n- ", $images);
            } else {
                $userParts[] = 'No images supplied — do not reference or invent any image URLs.';
            }
        }

        return [
            ['role' => 'system', 'content' => $this->systemPrompt($user)],
            ['role' => 'user',   'content' => implode("\n\n", $userParts)],
        ];
    }

    /** Decode the model output, tolerating fenced code blocks. */
    protected function parseJson(string $content): array
    {
        $raw = trim($content);
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $raw, $m)) {
            $raw = $m[1];
        }

        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('The AI response was not valid JSON. Your coins were refunded — please try again.');
        }

        return $parsed;
    }

    /** Keep only plausible http(s) URLs, capped. */
    protected function cleanUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            if (!is_string($url)) continue;
            $url = trim($url);
            if ($url === '' || mb_strlen($url) > 2048) continue;
            if (!preg_match('#^https?://#i', $url)) continue;
            $out[] = $url;
            if (count($out) >= self::MAX_LINKS) break;
        }
        return array_values(array_unique($out));
    }

    /** Image URLs may be absolute http(s) OR relative vault paths (/f/...). */
    protected function cleanImageUrls(array $urls): array
    {
        $out = [];
        foreach ($urls as $url) {
            if (!is_string($url)) continue;
            $url = trim($url);
            if ($url === '' || mb_strlen($url) > 2048) continue;
            if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '/')) continue;
            $out[] = $url;
            if (count($out) >= self::MAX_IMAGES) break;
        }
        return array_values(array_unique($out));
    }

    /** A supplied-image guard: only URLs the user actually provided pass. */
    protected function suppliedImage(?string $url, array $images): ?string
    {
        $url = is_string($url) ? trim($url) : '';
        return ($url !== '' && in_array($url, $images, true)) ? $url : null;
    }

    /** Clamp helper for model-provided strings. */
    protected function str(mixed $value, int $max): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** Clamp helper for model-provided prices. */
    protected function price(mixed $value): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;
        return max(0.0, min(9999999.0, round($n, 2)));
    }
}
