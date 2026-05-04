<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AiFeatureModelChange;
use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

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
    public const KEY_FEATURE_MODELS = 'ai.feature_models';

    // ── Voice Assistant (Whisper STT + GPT + ElevenLabs TTS) ──────
    public const KEY_VOICE_ENABLED          = 'ai.voice.enabled';
    public const KEY_VOICE_PLANS            = 'ai.voice.enabled_plans';
    public const KEY_VOICE_WHISPER_KEY_ENC  = 'ai.voice.whisper_api_key_enc';
    public const KEY_VOICE_WHISPER_MODEL    = 'ai.voice.whisper_model';
    public const KEY_VOICE_GPT_MODEL        = 'ai.voice.gpt_model';
    public const KEY_VOICE_ELEVEN_KEY_ENC   = 'ai.voice.elevenlabs_api_key_enc';
    public const KEY_VOICE_ELEVEN_VOICE_ID  = 'ai.voice.elevenlabs_voice_id';
    public const KEY_VOICE_ELEVEN_MODEL     = 'ai.voice.elevenlabs_model';
    public const KEY_VOICE_PRICE_STT        = 'ai.voice.price.stt_credits_per_minute';
    public const KEY_VOICE_PRICE_TTS        = 'ai.voice.price.tts_credits_per_1k_chars';
    public const KEY_VOICE_RATE_PER_MINUTE  = 'ai.voice.rate.turns_per_minute';

    public const DEFAULT_WHISPER_MODEL  = 'whisper-1';
    public const DEFAULT_VOICE_GPT      = 'gpt-4o-mini';
    public const DEFAULT_ELEVEN_MODEL   = 'eleven_turbo_v2_5';
    public const DEFAULT_ELEVEN_VOICE   = 'EXAVITQu4vr4xnSDxMaL';

    /** Chat features whose model is admin-configurable. */
    public const FEATURES = ['mind', 'persona', 'companion', 'coach', 'ask_coach', 'resume_import', 'resume_tailor', 'card_scan'];

    // ── Ask Coach (data-aware self-support chatbot) ───────────────
    public const KEY_ASK_COACH_PROMPT  = 'ai.ask_coach.system_prompt';
    public const KEY_ASK_COACH_PLANS   = 'ai.ask_coach.enabled_plans';

    public const DEFAULT_ASK_COACH_PROMPT = <<<'PROMPT'
You are 1INME Coach, a calm, concise self-support assistant for the user
who is chatting with you. The user is signed in to 1INME and is asking
questions about their own account, links, audience and analytics.

Rules you must follow:
- Ground every concrete claim in the live data the system gives you in
  the "Snapshots" block. If a question can't be answered from those
  snapshots, say so and suggest where to find it.
- Never invent numbers, dates, link URLs, names or revenue figures.
- Never reveal secrets or message bodies — counts only.
- Keep answers short (max ~150 words) and end with one specific next
  action when relevant.
- You are read-only. Do not promise to change settings, edit links,
  refund customers or contact anyone on the user's behalf.
PROMPT;

    public static function askCoachSystemPrompt(): string
    {
        $val = AppSetting::get(self::KEY_ASK_COACH_PROMPT);
        return is_string($val) && trim($val) !== '' ? $val : self::DEFAULT_ASK_COACH_PROMPT;
    }

    public static function setAskCoachSystemPrompt(?string $prompt): void
    {
        AppSetting::put(self::KEY_ASK_COACH_PROMPT, is_string($prompt) ? trim($prompt) : null);
    }

    /**
     * Plan slugs allowed to use Ask Coach. An empty list means
     * "every plan" (the default), so admins don't have to pre-flag
     * every plan when they enable the feature.
     *
     * @return list<string>
     */
    public static function askCoachEnabledPlans(): array
    {
        $val = AppSetting::get(self::KEY_ASK_COACH_PLANS);
        if (!is_array($val)) return [];
        return array_values(array_filter(array_map('strval', $val), fn($s) => $s !== ''));
    }

    public static function setAskCoachEnabledPlans(array $plans): void
    {
        $clean = array_values(array_unique(array_filter(array_map(
            fn($s) => preg_replace('/[^a-z0-9_-]/i', '', (string) $s), $plans
        ))));
        AppSetting::put(self::KEY_ASK_COACH_PLANS, $clean);
    }

    /**
     * Is the asker's plan allowed to use Ask Coach? Empty allow-list ==
     * everyone in. Free-tier users with no plan_id resolve to slug
     * "free" so admins can include/exclude them like any other plan.
     */
    public static function askCoachAllowedFor(\App\Modules\User\Models\User $user): bool
    {
        $allow = self::askCoachEnabledPlans();
        if (!$allow) return true;
        $slug = $user->plan_id && $user->plan ? (string) $user->plan->slug : 'free';
        return in_array($slug, $allow, true);
    }

    /** Fallback chat model used when a feature has no mapping yet. */
    public const DEFAULT_FEATURE_MODEL = 'gpt-4o-mini';

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

    /**
     * Per-feature chat model map. Always returns every known feature so
     * callers can rely on array_key access.
     *
     * @return array<string,string>
     */
    public static function featureModels(): array
    {
        $stored = AppSetting::get(self::KEY_FEATURE_MODELS);
        $out = [];
        foreach (self::FEATURES as $f) {
            $val = is_array($stored) && !empty($stored[$f]) && is_string($stored[$f])
                ? trim($stored[$f])
                : self::DEFAULT_FEATURE_MODEL;
            $out[$f] = $val;
        }
        return $out;
    }

    /**
     * Resolve the chat model for a given feature, falling back to the
     * default if the feature is unknown or unset.
     */
    public static function featureModel(string $feature): string
    {
        $map = self::featureModels();
        return $map[$feature] ?? self::DEFAULT_FEATURE_MODEL;
    }

    /**
     * Persist the per-feature model map and append an audit row for every
     * feature whose model actually changed. `$adminId` / `$adminName`
     * identify who made the change so cost regressions are traceable.
     *
     * @param array<string,string|null> $map
     */
    public static function setFeatureModels(array $map, ?int $adminId = null, ?string $adminName = null): void
    {
        $previous = self::featureModels();

        $clean = [];
        foreach (self::FEATURES as $f) {
            if (array_key_exists($f, $map) && is_string($map[$f]) && $map[$f] !== '') {
                $clean[$f] = trim($map[$f]);
            }
        }

        // Wrap the setting write and the audit insert in one transaction
        // so a row in the history table can never disagree with the
        // actually-stored mapping.
        DB::transaction(function () use ($clean, $previous, $adminId, $adminName) {
            AppSetting::put(self::KEY_FEATURE_MODELS, $clean);

            $effective = self::featureModels();
            $rows = [];
            $now  = now();
            foreach (self::FEATURES as $f) {
                $old = $previous[$f] ?? null;
                $new = $effective[$f] ?? null;
                if ($old === $new) continue;
                $rows[] = [
                    'feature'    => $f,
                    'old_model'  => $old,
                    'new_model'  => $new,
                    'admin_id'   => $adminId,
                    'admin_name' => $adminName,
                    'created_at' => $now,
                ];
            }
            if ($rows) {
                AiFeatureModelChange::insert($rows);
            }
        });
    }

    /**
     * Most recent per-feature model changes for the admin history panel.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int,AiFeatureModelChange>
     */
    public static function recentFeatureModelChanges(int $limit = 20)
    {
        return AiFeatureModelChange::orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Diagnose a feature → model mapping for the admin UI.
     *
     * Returns ['ok'=>bool,'level'=>'ok|warn|error','message'=>?string].
     * Warns when the model is unknown, disabled, or not a chat model.
     *
     * @return array{ok:bool,level:string,message:?string}
     */
    public static function featureModelStatus(string $feature): array
    {
        return self::featureModelStatusFor($feature, self::featureModels(), self::models());
    }

    /**
     * Same as featureModelStatus() but operates on caller-supplied
     * models / feature_models arrays. Lets the controller validate a
     * pending save (post-normalization) before persisting it.
     *
     * @param array<string,string> $featureModels
     * @param array<int,array{name:string,kind:string,enabled:bool}> $models
     * @return array{ok:bool,level:string,message:?string}
     */
    public static function featureModelStatusFor(string $feature, array $featureModels, array $models): array
    {
        $name = $featureModels[$feature] ?? self::DEFAULT_FEATURE_MODEL;
        $cfg = null;
        foreach ($models as $m) {
            if (is_array($m) && isset($m['name']) && strcasecmp((string) $m['name'], $name) === 0) {
                $cfg = $m;
                break;
            }
        }
        if (!$cfg) {
            return ['ok' => false, 'level' => 'error',
                'message' => "Model \"{$name}\" is not in the models table — calls will fail."];
        }
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'level' => 'error',
                'message' => "Model \"{$name}\" is disabled — enable it above or pick another."];
        }
        if (($cfg['kind'] ?? 'chat') !== 'chat') {
            return ['ok' => false, 'level' => 'error',
                'message' => "Model \"{$name}\" is configured as {$cfg['kind']}, not chat."];
        }
        return ['ok' => true, 'level' => 'ok', 'message' => null];
    }

    // ─────────────────────────────────────────────────────────────
    // Voice Assistant accessors
    // ─────────────────────────────────────────────────────────────

    public static function voiceEnabled(): bool
    {
        return (bool) AppSetting::get(self::KEY_VOICE_ENABLED, false);
    }

    public static function setVoiceEnabled(bool $on): void
    {
        AppSetting::put(self::KEY_VOICE_ENABLED, $on);
    }

    /** @return list<string> Plan slugs allowed to use Voice; empty == all. */
    public static function voiceEnabledPlans(): array
    {
        $val = AppSetting::get(self::KEY_VOICE_PLANS);
        if (!is_array($val)) return [];
        return array_values(array_filter(array_map('strval', $val), fn($s) => $s !== ''));
    }

    public static function setVoiceEnabledPlans(array $plans): void
    {
        $clean = array_values(array_unique(array_filter(array_map(
            fn($s) => preg_replace('/[^a-z0-9_-]/i', '', (string) $s), $plans
        ))));
        AppSetting::put(self::KEY_VOICE_PLANS, $clean);
    }

    public static function voiceAllowedFor(\App\Modules\User\Models\User $user): bool
    {
        if (!self::voiceEnabled()) return false;
        $allow = self::voiceEnabledPlans();
        if (!$allow) return true;
        $slug = $user->plan_id && $user->plan ? (string) $user->plan->slug : 'free';
        return in_array($slug, $allow, true);
    }

    public static function whisperKey(): ?string
    {
        return self::decryptKey(self::KEY_VOICE_WHISPER_KEY_ENC) ?? self::openAiKey();
    }

    public static function setWhisperKey(?string $key): void
    {
        self::storeKey(self::KEY_VOICE_WHISPER_KEY_ENC, $key);
    }

    public static function maskedWhisperKey(): ?string
    {
        $enc = AppSetting::get(self::KEY_VOICE_WHISPER_KEY_ENC);
        if (!$enc) return null;
        $k = self::decryptKey(self::KEY_VOICE_WHISPER_KEY_ENC);
        return $k ? 'sk-•••••••' . substr($k, -4) : null;
    }

    public static function whisperModel(): string
    {
        $v = AppSetting::get(self::KEY_VOICE_WHISPER_MODEL);
        return is_string($v) && trim($v) !== '' ? trim($v) : self::DEFAULT_WHISPER_MODEL;
    }

    public static function setWhisperModel(?string $name): void
    {
        AppSetting::put(self::KEY_VOICE_WHISPER_MODEL, is_string($name) ? trim($name) : null);
    }

    public static function voiceGptModel(): string
    {
        $v = AppSetting::get(self::KEY_VOICE_GPT_MODEL);
        return is_string($v) && trim($v) !== '' ? trim($v) : self::DEFAULT_VOICE_GPT;
    }

    public static function setVoiceGptModel(?string $name): void
    {
        AppSetting::put(self::KEY_VOICE_GPT_MODEL, is_string($name) ? trim($name) : null);
    }

    public static function elevenLabsKey(): ?string
    {
        return self::decryptKey(self::KEY_VOICE_ELEVEN_KEY_ENC);
    }

    public static function setElevenLabsKey(?string $key): void
    {
        self::storeKey(self::KEY_VOICE_ELEVEN_KEY_ENC, $key);
    }

    public static function maskedElevenLabsKey(): ?string
    {
        $k = self::elevenLabsKey();
        if (!$k) return null;
        return '•••••••' . substr($k, -4);
    }

    public static function elevenLabsVoiceId(): string
    {
        $v = AppSetting::get(self::KEY_VOICE_ELEVEN_VOICE_ID);
        return is_string($v) && trim($v) !== '' ? trim($v) : self::DEFAULT_ELEVEN_VOICE;
    }

    public static function setElevenLabsVoiceId(?string $id): void
    {
        AppSetting::put(self::KEY_VOICE_ELEVEN_VOICE_ID, is_string($id) ? trim($id) : null);
    }

    public static function elevenLabsModel(): string
    {
        $v = AppSetting::get(self::KEY_VOICE_ELEVEN_MODEL);
        return is_string($v) && trim($v) !== '' ? trim($v) : self::DEFAULT_ELEVEN_MODEL;
    }

    public static function setElevenLabsModel(?string $name): void
    {
        AppSetting::put(self::KEY_VOICE_ELEVEN_MODEL, is_string($name) ? trim($name) : null);
    }

    /** Credits charged per minute of audio sent to Whisper. */
    public static function voiceSttCreditsPerMinute(): int
    {
        return max(0, (int) AppSetting::get(self::KEY_VOICE_PRICE_STT, 30));
    }

    public static function setVoiceSttCreditsPerMinute(int $n): void
    {
        AppSetting::put(self::KEY_VOICE_PRICE_STT, max(0, $n));
    }

    /** Credits charged per 1 000 characters of TTS reply. */
    public static function voiceTtsCreditsPer1kChars(): int
    {
        return max(0, (int) AppSetting::get(self::KEY_VOICE_PRICE_TTS, 50));
    }

    public static function setVoiceTtsCreditsPer1kChars(int $n): void
    {
        AppSetting::put(self::KEY_VOICE_PRICE_TTS, max(0, $n));
    }

    public static function voiceTurnsPerMinute(): int
    {
        return max(1, (int) AppSetting::get(self::KEY_VOICE_RATE_PER_MINUTE, 12));
    }

    public static function setVoiceTurnsPerMinute(int $n): void
    {
        AppSetting::put(self::KEY_VOICE_RATE_PER_MINUTE, max(1, $n));
    }

    private static function decryptKey(string $key): ?string
    {
        $enc = AppSetting::get($key);
        if (!$enc || !is_string($enc)) return null;
        try { return Crypt::decryptString($enc); } catch (\Throwable $e) { return null; }
    }

    private static function storeKey(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            AppSetting::put($key, null);
            return;
        }
        AppSetting::put($key, Crypt::encryptString($value));
    }
}
