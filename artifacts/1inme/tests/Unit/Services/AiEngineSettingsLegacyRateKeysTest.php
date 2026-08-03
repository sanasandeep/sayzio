<?php

namespace Tests\Unit\Services;

use App\Services\AI\AiEngineSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The credits→coins rename retired in_credits_per_1k/out_credits_per_1k.
 * Any caller still passing them would have its rates read as 0 coins —
 * silently free AI usage. setModels() must reject them loudly.
 *
 * These tests never reach AppSetting::put (the guard throws first),
 * so no database is required.
 */
class AiEngineSettingsLegacyRateKeysTest extends TestCase
{
    public static function legacyKeyProvider(): array
    {
        return [
            'legacy input key'  => ['in_credits_per_1k'],
            'legacy output key' => ['out_credits_per_1k'],
        ];
    }

    #[DataProvider('legacyKeyProvider')]
    public function test_set_models_rejects_legacy_credit_rate_keys(string $legacyKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($legacyKey);

        AiEngineSettings::setModels([
            [
                'name'    => 'gpt-5.6-terra',
                'kind'    => 'chat',
                'enabled' => true,
                $legacyKey => 5.0,
            ],
        ]);
    }

    public function test_set_models_rejects_legacy_key_even_alongside_new_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AiEngineSettings::setModels([
            [
                'name'               => 'gpt-5.6-sol',
                'kind'               => 'chat',
                'enabled'            => true,
                'in_coins_per_1k'    => 10.0,
                'out_coins_per_1k'   => 60.0,
                'in_credits_per_1k'  => 100.0,
            ],
        ]);
    }

    public function test_legacy_rate_keys_constant_matches_retired_names(): void
    {
        $this->assertSame(
            ['in_credits_per_1k', 'out_credits_per_1k'],
            AiEngineSettings::LEGACY_RATE_KEYS
        );
    }
}
