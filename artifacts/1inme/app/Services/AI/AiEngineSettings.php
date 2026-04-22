<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed accessor for every admin-configurable AI Engine knob.
 *
 * All values live in the `app_settings` key/value store:
 *   ai.enabled                  bool                — master switch.
 *   ai.openai_api_key_enc       string              — Crypt-encrypted key.
 *   ai.models                   list<modelConfig>   — enabled model rates.
 *   ai.wallet_to_credits_rate   int                 — credits per 1 wallet coin.
 *   ai.credit_packs             list<packConfig>    — buyable packs.
 *
 * Keeping these helpers in one place stops every feature from
 * re-implementing key rotation, model gating, and pack lookup.
 */
class AiEngineSettings
{
    public const KEY_ENABLED        = 'ai.enabled';
    public const KEY_OPENAI_KEY_ENC = 'ai.openai_api_key_enc';
    public const KEY_MODELS         = 'ai.models';
    public const KEY_WALLET_RATE    = 'ai.wallet_to_credits_rate';
    public const KEY_PACKS          = 'ai.credit_packs';

    public static function isEnabled(): bool
    {
        return (bool) AppSetting::get(self::KEY_ENABLED, false);
    }

    public static function setEnabled(bool $on): void
    {
        AppSetting::put(self::KEY_ENABLED, $on);
    }

    /** Returns the decrypted OpenAI key or null if not set. */
    public static function openAiKey(): ?string
    {
        $enc = AppSetting::get(self::KEY_OPENAI_KEY_ENC);
        if (!$enc || !is_string($enc)) return null;
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function setOpenAiKey(?string $key): void
    {
        if ($key === null || $key === '') {
            AppSetting::put(self::KEY_OPENAI_KEY_ENC, null);
            return;
        }
        AppSetting::put(self::KEY_OPENAI_KEY_ENC, Crypt::encryptString($key));
    }

    /** Mask the stored key for display in admin UI: sk-...AbCd. */
    public static function maskedOpenAiKey(): ?string
    {
        $k = self::openAiKey();
        if (!$k) return null;
        $tail = substr($k, -4);
        return 'sk-•••••••' . $tail;
    }

    /**
     * @return array<int, array{name:string,kind:string,enabled:bool,in_credits_per_1k:int,out_credits_per_1k:int}>
     */
    public static function models(): array
    {
        $stored = AppSetting::get(self::KEY_MODELS);
        if (!is_array($stored) || !$stored) return self::defaultModels();
        $out = [];
        foreach ($stored as $m) {
            if (!is_array($m) || empty($m['name'])) continue;
            $out[] = [
                'name'                => (string) $m['name'],
                'kind'                => (string) ($m['kind'] ?? 'chat'),
                'enabled'             => (bool) ($m['enabled'] ?? true),
                'in_credits_per_1k'   => max(0, (int) ($m['in_credits_per_1k'] ?? 0)),
                'out_credits_per_1k'  => max(0, (int) ($m['out_credits_per_1k'] ?? 0)),
            ];
        }
        return $out ?: self::defaultModels();
    }

    /** Sensible defaults so the engine works the moment a key is added. */
    public static function defaultModels(): array
    {
        return [
            ['name' => 'gpt-4o',                  'kind' => 'chat',      'enabled' => true,  'in_credits_per_1k' => 50,  'out_credits_per_1k' => 150],
            ['name' => 'gpt-4o-mini',             'kind' => 'chat',      'enabled' => true,  'in_credits_per_1k' => 5,   'out_credits_per_1k' => 15],
            ['name' => 'text-embedding-3-small',  'kind' => 'embedding', 'enabled' => true,  'in_credits_per_1k' => 1,   'out_credits_per_1k' => 0],
        ];
    }

    public static function setModels(array $models): void
    {
        $clean = [];
        foreach ($models as $m) {
            if (!is_array($m) || empty($m['name'])) continue;
            $clean[] = [
                'name'                => trim((string) $m['name']),
                'kind'                => in_array(($m['kind'] ?? 'chat'), ['chat','embedding'], true) ? $m['kind'] : 'chat',
                'enabled'             => (bool) ($m['enabled'] ?? false),
                'in_credits_per_1k'   => max(0, (int) ($m['in_credits_per_1k'] ?? 0)),
                'out_credits_per_1k'  => max(0, (int) ($m['out_credits_per_1k'] ?? 0)),
            ];
        }
        AppSetting::put(self::KEY_MODELS, $clean);
    }

    /** @return array{name:string,kind:string,enabled:bool,in_credits_per_1k:int,out_credits_per_1k:int}|null */
    public static function model(string $name): ?array
    {
        foreach (self::models() as $m) {
            if (strcasecmp($m['name'], $name) === 0) return $m;
        }
        return null;
    }

    /** Wallet-coins-to-AI-credits conversion factor (credits per 1 coin). */
    public static function walletToCreditsRate(): int
    {
        return max(1, (int) AppSetting::get(self::KEY_WALLET_RATE, 10));
    }

    public static function setWalletToCreditsRate(int $rate): void
    {
        AppSetting::put(self::KEY_WALLET_RATE, max(1, $rate));
    }

    /**
     * @return array<int, array{id:string,label:string,credits:int,wallet_cost:int}>
     */
    public static function packs(): array
    {
        $stored = AppSetting::get(self::KEY_PACKS);
        if (!is_array($stored) || !$stored) return self::defaultPacks();
        $out = [];
        foreach ($stored as $p) {
            if (!is_array($p) || empty($p['id'])) continue;
            $out[] = [
                'id'          => (string) $p['id'],
                'label'       => (string) ($p['label'] ?? $p['id']),
                'credits'     => max(0, (int) ($p['credits'] ?? 0)),
                'wallet_cost' => max(0, (int) ($p['wallet_cost'] ?? 0)),
            ];
        }
        return $out ?: self::defaultPacks();
    }

    public static function defaultPacks(): array
    {
        return [
            ['id' => 'small',  'label' => 'Starter',     'credits' => 1000,  'wallet_cost' => 100],
            ['id' => 'medium', 'label' => 'Creator',     'credits' => 5000,  'wallet_cost' => 450],
            ['id' => 'large',  'label' => 'Power user',  'credits' => 25000, 'wallet_cost' => 2000],
        ];
    }

    public static function setPacks(array $packs): void
    {
        $clean = [];
        foreach ($packs as $p) {
            if (!is_array($p) || empty($p['id'])) continue;
            $clean[] = [
                'id'          => preg_replace('/[^a-z0-9_-]/i', '', (string) $p['id']),
                'label'       => trim((string) ($p['label'] ?? $p['id'])),
                'credits'     => max(1, (int) ($p['credits'] ?? 0)),
                'wallet_cost' => max(1, (int) ($p['wallet_cost'] ?? 0)),
            ];
        }
        AppSetting::put(self::KEY_PACKS, $clean);
    }

    public static function pack(string $id): ?array
    {
        foreach (self::packs() as $p) {
            if ($p['id'] === $id) return $p;
        }
        return null;
    }
}
