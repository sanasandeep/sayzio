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
 *   ai.models                   list<modelConfig>   — enabled model rates,
 *                                                     priced in coins per
 *                                                     1 000 tokens (float).
 *
 * AI usage is billed straight from the coin wallet at call time — there
 * is no separate AI-credit balance, exchange rate, or buyable packs.
 *
 * Keeping these helpers in one place stops every feature from
 * re-implementing key rotation and model gating.
 */
class AiEngineSettings
{
    public const KEY_ENABLED        = 'ai.enabled';
    public const KEY_OPENAI_KEY_ENC = 'ai.openai_api_key_enc';
    public const KEY_MODELS         = 'ai.models';
    public const KEY_FEATURE_MODELS = 'ai.feature_models';

    // ── AI Artistic QR (Replicate QR-ControlNet) ──────────────────
    // Replicate token (Crypt-encrypted) with an env fallback, plus the
    // admin-configurable per-generation coin price for this image API.
    public const KEY_REPLICATE_KEY_ENC = 'ai.replicate_api_key_enc';
    public const KEY_QR_ART_COINS      = 'ai.qr_art.coins_per_generation';
    public const DEFAULT_QR_ART_COINS  = 20;

    // ── Voice Assistant (Whisper STT + GPT + ElevenLabs TTS) ──────
    public const KEY_VOICE_ENABLED          = 'ai.voice.enabled';
    public const KEY_VOICE_PLANS            = 'ai.voice.enabled_plans';
    public const KEY_VOICE_WHISPER_KEY_ENC  = 'ai.voice.whisper_api_key_enc';
    public const KEY_VOICE_WHISPER_MODEL    = 'ai.voice.whisper_model';
    public const KEY_VOICE_GPT_MODEL        = 'ai.voice.gpt_model';
    public const KEY_VOICE_ELEVEN_KEY_ENC   = 'ai.voice.elevenlabs_api_key_enc';
    public const KEY_VOICE_ELEVEN_VOICE_ID  = 'ai.voice.elevenlabs_voice_id';
    public const KEY_VOICE_ELEVEN_MODEL     = 'ai.voice.elevenlabs_model';
    public const KEY_VOICE_PRICE_STT        = 'ai.voice.price.stt_coins_per_minute';
    public const KEY_VOICE_PRICE_TTS        = 'ai.voice.price.tts_coins_per_1k_chars';
    public const KEY_VOICE_RATE_PER_MINUTE  = 'ai.voice.rate.turns_per_minute';

    public const DEFAULT_WHISPER_MODEL  = 'whisper-1';
    public const DEFAULT_VOICE_GPT      = 'gpt-4o-mini';
    public const DEFAULT_ELEVEN_MODEL   = 'eleven_turbo_v2_5';
    public const DEFAULT_ELEVEN_VOICE   = 'EXAVITQu4vr4xnSDxMaL';

    /** Chat features whose model is admin-configurable. */
    public const FEATURES = ['mind', 'persona', 'companion', 'coach', 'ask_coach', 'resume_import', 'resume_tailor', 'resume_cover_letter', 'card_scan', 'biolink_builder', 'inbox_agent', 'brand_kit', 'whatsapp_agent', 'marketing_strategist', 'ai_staff_billing', 'ai_staff_contacts', 'ai_staff_general', 'dashboard_designer', 'competitor_teardown'];

    // ── Ask Coach (data-aware self-support chatbot) ───────────────
    public const KEY_ASK_COACH_PROMPT  = 'ai.ask_coach.system_prompt';
    public const KEY_ASK_COACH_PLANS   = 'ai.ask_coach.enabled_plans';

    // Behavior controls
    public const KEY_ASK_COACH_TONE            = 'ai.ask_coach.behavior.tone';
    public const KEY_ASK_COACH_RESPONSE_LENGTH = 'ai.ask_coach.behavior.response_length';
    public const KEY_ASK_COACH_REPLY_LANGUAGE  = 'ai.ask_coach.behavior.reply_language';
    public const KEY_ASK_COACH_TEMPERATURE     = 'ai.ask_coach.behavior.temperature';

    // Usage limits
    public const KEY_ASK_COACH_PLAN_CAPS         = 'ai.ask_coach.limits.plan_caps';
    public const KEY_ASK_COACH_COOLDOWN          = 'ai.ask_coach.limits.cooldown_seconds';
    public const KEY_ASK_COACH_CREDIT_MULTIPLIER = 'ai.ask_coach.limits.credit_multiplier';

    // Content controls
    public const KEY_ASK_COACH_BANNED_TOPICS   = 'ai.ask_coach.content.banned_topics';
    public const KEY_ASK_COACH_GREETING        = 'ai.ask_coach.content.greeting';
    public const KEY_ASK_COACH_FALLBACK_MSG    = 'ai.ask_coach.content.fallback_message';
    public const KEY_ASK_COACH_ESCALATION_NOTE = 'ai.ask_coach.content.escalation_note';

    // Model & data settings
    public const KEY_ASK_COACH_MAX_TOKENS           = 'ai.ask_coach.model.max_tokens';
    public const KEY_ASK_COACH_SNAPSHOT_CATEGORIES  = 'ai.ask_coach.model.snapshot_categories';

    /** Default max tokens when not admin-overridden. */
    public const DEFAULT_ASK_COACH_MAX_TOKENS = 600;
    /** Default temperature when not admin-overridden. */
    public const DEFAULT_ASK_COACH_TEMPERATURE = 0.4;

    /** All snapshot category slugs the admin can toggle. */
    public const ASK_COACH_SNAPSHOT_CATEGORIES = ['links', 'analytics', 'audience', 'billing', 'events'];

    public const DEFAULT_ASK_COACH_PROMPT = <<<'PROMPT'
You are Sayzio Coach, a calm, concise self-support assistant for the user
who is chatting with you. The user is signed in to Sayzio and is asking
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

    /**
     * Cheapest active plan that would let $user use Ask Coach, so the AI
     * gate page can point them at a concrete self-serve upgrade instead
     * of a support email. Returns null when the user is already allowed,
     * the allow-list is empty (everyone in), or no active plan matches.
     */
    public static function askCoachUpgradePlanFor(\App\Modules\User\Models\User $user): ?\App\Modules\Admin\Models\Plan
    {
        if (self::askCoachAllowedFor($user)) return null;
        $allow = self::askCoachEnabledPlans();
        if (!$allow) return null;
        return \App\Modules\Admin\Models\Plan::where('status', 'active')
            ->public()
            ->whereIn('slug', $allow)
            ->orderBy('monthly_price')
            ->first();
    }

    // ── Ask Coach — behavior controls ────────────────────────────────

    /** Tone preset: friendly|professional|concise|playful. Empty = default (friendly). */
    public static function askCoachTone(): string
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_TONE);
        $valid = ['friendly', 'professional', 'concise', 'playful'];
        return (is_string($v) && in_array($v, $valid, true)) ? $v : 'friendly';
    }

    public static function setAskCoachTone(?string $tone): void
    {
        $valid = ['friendly', 'professional', 'concise', 'playful'];
        AppSetting::put(self::KEY_ASK_COACH_TONE, (is_string($tone) && in_array($tone, $valid, true)) ? $tone : null);
    }

    /** Response length hint: short|medium|long. Empty = default (medium). */
    public static function askCoachResponseLength(): string
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_RESPONSE_LENGTH);
        $valid = ['short', 'medium', 'long'];
        return (is_string($v) && in_array($v, $valid, true)) ? $v : 'medium';
    }

    public static function setAskCoachResponseLength(?string $len): void
    {
        $valid = ['short', 'medium', 'long'];
        AppSetting::put(self::KEY_ASK_COACH_RESPONSE_LENGTH, (is_string($len) && in_array($len, $valid, true)) ? $len : null);
    }

    /** Preferred reply language. 'match_user' = auto-detect (default). */
    public static function askCoachReplyLanguage(): string
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_REPLY_LANGUAGE);
        return (is_string($v) && trim($v) !== '') ? trim($v) : 'match_user';
    }

    public static function setAskCoachReplyLanguage(?string $lang): void
    {
        AppSetting::put(self::KEY_ASK_COACH_REPLY_LANGUAGE, is_string($lang) ? trim($lang) : null);
    }

    /** Temperature for Coach chat completions (0.0–1.5). */
    public static function askCoachTemperature(): float
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_TEMPERATURE);
        return ($v !== null && is_numeric($v))
            ? max(0.0, min(1.5, (float) $v))
            : self::DEFAULT_ASK_COACH_TEMPERATURE;
    }

    public static function setAskCoachTemperature(?float $t): void
    {
        AppSetting::put(self::KEY_ASK_COACH_TEMPERATURE, $t !== null ? round(max(0.0, min(1.5, $t)), 2) : null);
    }

    // ── Ask Coach — usage limits ──────────────────────────────────────

    /**
     * Per-plan message caps. Returns a map of plan_slug =>
     * ['period' => 'daily'|'monthly', 'cap' => int].
     * An empty cap means unlimited for that plan.
     *
     * @return array<string, array{period:string,cap:int}>
     */
    public static function askCoachPlanCaps(): array
    {
        $val = AppSetting::get(self::KEY_ASK_COACH_PLAN_CAPS);
        if (!is_array($val)) return [];
        $out = [];
        foreach ($val as $slug => $cfg) {
            if (!is_string($slug) || $slug === '' || !is_array($cfg)) continue;
            $period = in_array($cfg['period'] ?? '', ['daily', 'monthly'], true) ? $cfg['period'] : 'daily';
            $cap = max(0, (int) ($cfg['cap'] ?? 0));
            if ($cap > 0) {
                $out[$slug] = ['period' => $period, 'cap' => $cap];
            }
        }
        return $out;
    }

    /** @param array<string, array{period:string,cap:int}> $caps */
    public static function setAskCoachPlanCaps(array $caps): void
    {
        $clean = [];
        foreach ($caps as $slug => $cfg) {
            if (!is_string($slug) || $slug === '' || !is_array($cfg)) continue;
            $period = in_array($cfg['period'] ?? '', ['daily', 'monthly'], true) ? $cfg['period'] : 'daily';
            $cap = max(0, (int) ($cfg['cap'] ?? 0));
            if ($cap > 0) {
                $clean[$slug] = ['period' => $period, 'cap' => $cap];
            }
        }
        AppSetting::put(self::KEY_ASK_COACH_PLAN_CAPS, $clean ?: null);
    }

    /** Per-user cooldown between messages in seconds. 0 = no cooldown. */
    public static function askCoachCooldownSeconds(): int
    {
        return max(0, (int) AppSetting::get(self::KEY_ASK_COACH_COOLDOWN, 0));
    }

    public static function setAskCoachCooldownSeconds(int $secs): void
    {
        AppSetting::put(self::KEY_ASK_COACH_COOLDOWN, max(0, $secs) ?: null);
    }

    /**
     * Credit-cost multiplier applied on top of the per-plan coin rate.
     * 1.0 = no surcharge (default). 1.5 = 50% surcharge on base coin cost.
     */
    public static function askCoachCreditMultiplier(): float
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_CREDIT_MULTIPLIER);
        return ($v !== null && is_numeric($v)) ? max(1.0, (float) $v) : 1.0;
    }

    public static function setAskCoachCreditMultiplier(?float $m): void
    {
        AppSetting::put(self::KEY_ASK_COACH_CREDIT_MULTIPLIER, ($m !== null && $m > 1.0) ? round($m, 2) : null);
    }

    // ── Ask Coach — content controls ──────────────────────────────────

    /**
     * Banned topic keywords/phrases. When any keyword appears in the user's
     * message the Coach politely declines instead of calling the model.
     *
     * @return list<string>
     */
    public static function askCoachBannedTopics(): array
    {
        $val = AppSetting::get(self::KEY_ASK_COACH_BANNED_TOPICS);
        if (!is_array($val)) return [];
        return array_values(array_filter(array_map('trim', array_map('strval', $val))));
    }

    public static function setAskCoachBannedTopics(array $topics): void
    {
        $clean = array_values(array_filter(array_map('trim', array_map('strval', $topics))));
        AppSetting::put(self::KEY_ASK_COACH_BANNED_TOPICS, $clean ?: null);
    }

    /** Custom greeting shown as the first message in a new chat. Empty = no greeting. */
    public static function askCoachGreeting(): string
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_GREETING);
        return is_string($v) ? trim($v) : '';
    }

    public static function setAskCoachGreeting(?string $msg): void
    {
        AppSetting::put(self::KEY_ASK_COACH_GREETING, (is_string($msg) && trim($msg) !== '') ? trim($msg) : null);
    }

    /** Custom fallback message when Coach can't answer. Empty = platform default. */
    public static function askCoachFallbackMessage(): string
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_FALLBACK_MSG);
        return (is_string($v) && trim($v) !== '') ? trim($v) : '';
    }

    public static function setAskCoachFallbackMessage(?string $msg): void
    {
        AppSetting::put(self::KEY_ASK_COACH_FALLBACK_MSG, (is_string($msg) && trim($msg) !== '') ? trim($msg) : null);
    }

    /**
     * Optional note appended to decline messages pointing users to support.
     * Empty = no escalation note.
     */
    public static function askCoachEscalationNote(): string
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_ESCALATION_NOTE);
        return (is_string($v) && trim($v) !== '') ? trim($v) : '';
    }

    public static function setAskCoachEscalationNote(?string $note): void
    {
        AppSetting::put(self::KEY_ASK_COACH_ESCALATION_NOTE, (is_string($note) && trim($note) !== '') ? trim($note) : null);
    }

    // ── Ask Coach — model & data settings ────────────────────────────

    /** Max tokens for Coach completions. Falls back to DEFAULT_ASK_COACH_MAX_TOKENS. */
    public static function askCoachMaxTokens(): int
    {
        $v = AppSetting::get(self::KEY_ASK_COACH_MAX_TOKENS);
        return ($v !== null && is_numeric($v)) ? max(100, min(4000, (int) $v)) : self::DEFAULT_ASK_COACH_MAX_TOKENS;
    }

    public static function setAskCoachMaxTokens(?int $n): void
    {
        AppSetting::put(self::KEY_ASK_COACH_MAX_TOKENS, ($n !== null) ? max(100, min(4000, $n)) : null);
    }

    /**
     * Enabled snapshot categories. Empty list = all categories on (default).
     * See ASK_COACH_SNAPSHOT_CATEGORIES for the full set.
     *
     * @return list<string>
     */
    public static function askCoachSnapshotCategories(): array
    {
        $val = AppSetting::get(self::KEY_ASK_COACH_SNAPSHOT_CATEGORIES);
        if (!is_array($val)) return [];
        return array_values(array_filter(
            array_map('strval', $val),
            fn($s) => in_array($s, self::ASK_COACH_SNAPSHOT_CATEGORIES, true)
        ));
    }

    public static function setAskCoachSnapshotCategories(array $cats): void
    {
        $clean = array_values(array_filter(
            array_map('strval', $cats),
            fn($s) => in_array($s, self::ASK_COACH_SNAPSHOT_CATEGORIES, true)
        ));
        AppSetting::put(self::KEY_ASK_COACH_SNAPSHOT_CATEGORIES, $clean ?: null);
    }

    /**
     * Build the behavior directive block appended to the system prompt
     * based on the current tone/length/language settings.
     */
    public static function askCoachBehaviorDirectives(): string
    {
        $parts = [];

        $toneMap = [
            'friendly'     => 'Adopt a warm, friendly, encouraging tone.',
            'professional' => 'Adopt a professional, formal, and neutral tone.',
            'concise'      => 'Be extremely concise — prioritize brevity over detail.',
            'playful'      => 'Use a light, playful, and upbeat tone while staying helpful.',
        ];
        $tone = self::askCoachTone();
        if (isset($toneMap[$tone]) && $tone !== 'friendly') {
            $parts[] = $toneMap[$tone];
        }

        $lengthMap = [
            'short'  => 'Keep responses very short — max ~60 words, no lists.',
            'medium' => '',
            'long'   => 'You may give longer, more detailed answers — up to ~300 words — when a thorough explanation helps.',
        ];
        $length = self::askCoachResponseLength();
        if (!empty($lengthMap[$length])) {
            $parts[] = $lengthMap[$length];
        }

        $lang = self::askCoachReplyLanguage();
        if ($lang !== 'match_user' && $lang !== '') {
            $parts[] = "Always reply in language code: {$lang}.";
        }

        if (!$parts) return '';
        return "\n\nBehavior directives:\n- " . implode("\n- ", $parts);
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
     * Per-model token pricing in COINS per 1 000 tokens. Rates are
     * fractional (e.g. 0.5 coins / 1k input tokens); OpenAiService sums
     * the exact float cost and ceil()s to whole coins at charge time.
     *
     * @return array<int, array{name:string,kind:string,enabled:bool,in_coins_per_1k:float,out_coins_per_1k:float}>
     */
    public static function models(): array
    {
        $stored = AppSetting::get(self::KEY_MODELS);
        if (!is_array($stored) || !$stored) return self::defaultModels();
        $out = [];
        foreach ($stored as $m) {
            if (!is_array($m) || empty($m['name'])) continue;
            $out[] = [
                'name'              => (string) $m['name'],
                'kind'              => (string) ($m['kind'] ?? 'chat'),
                'enabled'           => (bool) ($m['enabled'] ?? true),
                'in_coins_per_1k'   => max(0.0, (float) ($m['in_coins_per_1k'] ?? 0)),
                'out_coins_per_1k'  => max(0.0, (float) ($m['out_coins_per_1k'] ?? 0)),
            ];
        }
        return $out ?: self::defaultModels();
    }

    /** Sensible defaults so the engine works the moment a key is added. */
    public static function defaultModels(): array
    {
        return [
            ['name' => 'gpt-4o',                  'kind' => 'chat',      'enabled' => true,  'in_coins_per_1k' => 5.0,  'out_coins_per_1k' => 15.0],
            ['name' => 'gpt-4o-mini',             'kind' => 'chat',      'enabled' => true,  'in_coins_per_1k' => 0.5,  'out_coins_per_1k' => 1.5],
            ['name' => 'text-embedding-3-small',  'kind' => 'embedding', 'enabled' => true,  'in_coins_per_1k' => 0.1,  'out_coins_per_1k' => 0.0],
        ];
    }

    public static function setModels(array $models): void
    {
        $clean = [];
        foreach ($models as $m) {
            if (!is_array($m) || empty($m['name'])) continue;
            $clean[] = [
                'name'              => trim((string) $m['name']),
                'kind'              => in_array(($m['kind'] ?? 'chat'), ['chat','embedding'], true) ? $m['kind'] : 'chat',
                'enabled'           => (bool) ($m['enabled'] ?? false),
                'in_coins_per_1k'   => round(max(0.0, (float) ($m['in_coins_per_1k'] ?? 0)), 4),
                'out_coins_per_1k'  => round(max(0.0, (float) ($m['out_coins_per_1k'] ?? 0)), 4),
            ];
        }
        AppSetting::put(self::KEY_MODELS, $clean);
    }

    /** @return array{name:string,kind:string,enabled:bool,in_coins_per_1k:float,out_coins_per_1k:float}|null */
    public static function model(string $name): ?array
    {
        foreach (self::models() as $m) {
            if (strcasecmp($m['name'], $name) === 0) return $m;
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

    /**
     * Cheapest active plan that would let $user use the Voice Assistant,
     * so the AI gate page can point them at a concrete self-serve upgrade
     * instead of a silent 403. Mirrors {@see askCoachUpgradePlanFor}.
     * Returns null when the user is already allowed, the allow-list is
     * empty (everyone in), or no active plan matches.
     */
    public static function voiceUpgradePlanFor(\App\Modules\User\Models\User $user): ?\App\Modules\Admin\Models\Plan
    {
        if (self::voiceAllowedFor($user)) return null;
        $allow = self::voiceEnabledPlans();
        if (!$allow) return null;
        return \App\Modules\Admin\Models\Plan::where('status', 'active')
            ->public()
            ->whereIn('slug', $allow)
            ->orderBy('monthly_price')
            ->first();
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

    /** Coins charged per minute of audio sent to Whisper (fractional). */
    public static function voiceSttCoinsPerMinute(): float
    {
        return max(0.0, (float) AppSetting::get(self::KEY_VOICE_PRICE_STT, 3.0));
    }

    public static function setVoiceSttCoinsPerMinute(float $n): void
    {
        AppSetting::put(self::KEY_VOICE_PRICE_STT, round(max(0.0, $n), 4));
    }

    /** Coins charged per 1 000 characters of TTS reply (fractional). */
    public static function voiceTtsCoinsPer1kChars(): float
    {
        return max(0.0, (float) AppSetting::get(self::KEY_VOICE_PRICE_TTS, 5.0));
    }

    public static function setVoiceTtsCoinsPer1kChars(float $n): void
    {
        AppSetting::put(self::KEY_VOICE_PRICE_TTS, round(max(0.0, $n), 4));
    }

    public static function voiceTurnsPerMinute(): int
    {
        return max(1, (int) AppSetting::get(self::KEY_VOICE_RATE_PER_MINUTE, 12));
    }

    public static function setVoiceTurnsPerMinute(int $n): void
    {
        AppSetting::put(self::KEY_VOICE_RATE_PER_MINUTE, max(1, $n));
    }

    // ─────────────────────────────────────────────────────────────
    // AI Artistic QR (Replicate) accessors
    // ─────────────────────────────────────────────────────────────

    /**
     * Decrypted Replicate API token. Prefers the admin-stored (encrypted)
     * key and falls back to the deploy-time `services.replicate.api_token`
     * (REPLICATE_API_TOKEN env) so the feature works either way. Returns
     * null when neither is set — callers treat that as preview/disabled.
     */
    public static function replicateKey(): ?string
    {
        $stored = self::decryptKey(self::KEY_REPLICATE_KEY_ENC);
        if ($stored !== null && $stored !== '') {
            return $stored;
        }
        $fallback = config('services.replicate.api_token');
        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public static function setReplicateKey(?string $key): void
    {
        self::storeKey(self::KEY_REPLICATE_KEY_ENC, $key);
    }

    /** Whether an admin-supplied key is stored (vs falling back to env). */
    public static function hasStoredReplicateKey(): bool
    {
        return self::decryptKey(self::KEY_REPLICATE_KEY_ENC) !== null;
    }

    public static function maskedReplicateKey(): ?string
    {
        $k = self::replicateKey();
        if (!$k) return null;
        return 'r8_•••••••' . substr($k, -4);
    }

    /** Admin-set coins charged per AI Artistic QR generation (>= 1). */
    public static function qrArtCoinsPerGeneration(): int
    {
        return max(1, (int) AppSetting::get(self::KEY_QR_ART_COINS, self::DEFAULT_QR_ART_COINS));
    }

    public static function setQrArtCoinsPerGeneration(int $n): void
    {
        AppSetting::put(self::KEY_QR_ART_COINS, max(1, $n));
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
