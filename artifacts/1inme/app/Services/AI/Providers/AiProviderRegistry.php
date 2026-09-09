<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiEngineSettings;

/**
 * The list of providers, and the one place that turns a slug into a driver.
 *
 * Drivers are memoised per slug because they are stateless and each one
 * resolving its key means an AppSetting read plus a decrypt; a single chat
 * turn asks for its driver several times over.
 */
class AiProviderRegistry
{
    public const OPENAI     = 'openai';
    public const OPENROUTER = 'openrouter';
    public const ANTHROPIC  = 'anthropic';

    /** Every provider slug, in the order the admin sees them. */
    public const ALL = [self::OPENAI, self::OPENROUTER, self::ANTHROPIC];

    /** @var array<string,AiProviderDriver> */
    protected static array $cache = [];

    /** @var array<string,class-string<AiProviderDriver>> */
    protected const DRIVERS = [
        self::OPENAI     => OpenAiDriver::class,
        self::OPENROUTER => OpenRouterDriver::class,
        self::ANTHROPIC  => AnthropicDriver::class,
    ];

    public static function driver(string $slug): AiProviderDriver
    {
        $slug = self::normalise($slug);

        return self::$cache[$slug] ??= new (self::DRIVERS[$slug])();
    }

    /**
     * Unknown slugs resolve to OpenAI rather than throwing.
     *
     * Model rows written before providers existed carry no provider at all,
     * and they are all OpenAI models. Throwing would take the whole engine
     * down on an upgrade; defaulting keeps every existing row working
     * exactly as it did.
     */
    public static function normalise(?string $slug): string
    {
        $slug = strtolower(trim((string) $slug));

        return isset(self::DRIVERS[$slug]) ? $slug : self::OPENAI;
    }

    /** @return array<string,string> slug => label, for admin selects */
    public static function labels(): array
    {
        $out = [];
        foreach (self::ALL as $slug) {
            $out[$slug] = self::driver($slug)->label();
        }

        return $out;
    }

    /** Providers that actually have a key configured. */
    public static function configured(): array
    {
        return array_values(array_filter(
            self::ALL,
            fn (string $s) => (bool) self::driver($s)->key(),
        ));
    }

    /**
     * The fallback order for a given starting provider: the provider itself
     * first, then the admin's configured chain, then anything else that has
     * a key. Providers without a key are dropped -- trying them would burn a
     * round trip to produce the same failure every time.
     *
     * @return array<int,string>
     */
    public static function fallbackChain(string $from): array
    {
        $from  = self::normalise($from);
        $chain = array_merge([$from], AiEngineSettings::providerFallbackOrder(), self::ALL);

        $seen = [];
        $out  = [];
        foreach ($chain as $slug) {
            $slug = self::normalise($slug);
            if (isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            if ($slug === $from || self::driver($slug)->key()) {
                $out[] = $slug;
            }
        }

        return $out;
    }

    /** Test-seam: drop memoised drivers so a re-keyed provider is picked up. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
