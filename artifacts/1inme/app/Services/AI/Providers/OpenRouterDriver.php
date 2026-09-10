<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiEngineSettings;

/**
 * OpenRouter.
 *
 * It serves OpenAI's /chat/completions contract verbatim -- same request
 * body, same response shape, same SSE frames -- so everything except the
 * base URL, the key and two headers is inherited rather than copied. If the
 * two protocols ever diverge, the override goes here and nothing else moves.
 *
 * The two extra headers are OpenRouter's attribution mechanism: they decide
 * how the app is credited on their public rankings. They are optional to the
 * API and required by good manners.
 *
 * Model names carry a vendor prefix ("anthropic/claude-sonnet-4",
 * "google/gemini-2.5-pro"), which is what makes OpenRouter useful here: it
 * reaches models no direct key covers, on one bill, without another driver.
 */
class OpenRouterDriver extends OpenAiDriver
{
    protected const BASE_URL = 'https://openrouter.ai/api/v1';

    public function slug(): string
    {
        return 'openrouter';
    }

    public function label(): string
    {
        return 'OpenRouter';
    }

    public function key(): ?string
    {
        return AiEngineSettings::openRouterKey();
    }

    /**
     * OpenRouter proxies many vendors, and not all of them expose an
     * embeddings endpoint through it. Embeddings stay on a direct key.
     */
    public function supportsEmbeddings(): bool
    {
        return false;
    }

    public function embed(string $model, array $inputs, array $opts = []): array
    {
        throw new AiProviderException(
            'OpenRouter does not serve embeddings; point the embedding model at OpenAI.',
            400,
            $this->slug(),
        );
    }

    protected function headers(): array
    {
        return [
            'HTTP-Referer' => config('app.url', 'https://sayzio.app'),
            'X-Title'      => config('app.name', 'Sayzio'),
        ];
    }
}
