<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AppSetting;

/**
 * Typed accessor for the site-wide AI assistant configuration.
 * All values live in the shared app_settings store.
 */
class SiteAssistantSettings
{
    public const KEY = 'site_assistant.config';

    public static function defaults(): array
    {
        return [
            'enabled_marketing' => true,
            'enabled_app'       => true,
            'launcher_position' => 'bottom-right', // bottom-right|bottom-left
            'accent_color'      => '#7c3aed',
            'avatar_url'        => null,
            'greeting'          => "Hi! I'm your 1INME assistant. Ask me anything about the platform or what you can do on this page.",
            'system_prompt'     => self::defaultSystemPrompt(),
            'model'             => '', // empty = fall back to feature-mapped chat model
            'mind_ids'          => [], // empty = use platform-default minds
            'assistant_mind_id' => null, // platform Mind that holds admin-curated assistant knowledge sources (auto-created on first use)
            'temperature'       => 0.4,
            'max_tokens'        => 800,
            'billing_user_id'   => null, // platform user that pays for anonymous turns
            'monthly_budget_credits' => 0, // 0 = unlimited
            'session_rate_per_minute' => 12,
            'handoff_enabled'   => true,
            'handoff_freeze_after' => true,
            // Low-balance pre-send warning. The runtime calls the visitor
            // "low" when their balance is below `multiplier * avg cost of
            // a reply`. Default reply cost is used as the fallback when
            // there's no historical signal yet (first turn).
            'low_balance_multiplier'      => 3,
            'low_balance_default_credits' => 50,
            'low_balance_message_signed_in' =>
                'Only enough credits left for about {remaining} more replies — top up to keep chatting.',
            'low_balance_message_anonymous' =>
                'Heads up — this chat is running low on credits and replies may be cut short soon.',
            // Optional per-locale overrides for the two messages above.
            // Shape: ['fr' => ['signed_in' => '…', 'anonymous' => '…'], …]
            // Empty/missing entries fall back to the default English copy.
            'low_balance_message_locales' => [],
            // CTA button label shown on the low-balance bubble. Empty
            // means "use the audience-specific built-in default" (Top
            // up for signed-in, See plans for anonymous). Per-locale
            // overrides take precedence when they match the visitor's
            // Accept-Language; otherwise this default is used.
            'low_balance_topup_label'         => '',
            'low_balance_topup_label_locales' => [],
            'starter_prompts'   => [
                'What can I do on this page?',
                'How does pricing work?',
                'Talk to a human',
            ],
            // Cut-off retry monitor — a scheduled job evaluates the
            // last 24h of partial/failed assistant streams and alerts
            // admins when the abandon rate (visitors who never clicked
            // Retry) crosses the configured threshold. Disabled by
            // default so existing installs are silent until an admin
            // opts in.
            'cutoff_alert_enabled'             => false,
            'cutoff_alert_abandon_threshold'   => 60,   // percent (0-100)
            'cutoff_alert_min_sample'          => 20,   // need this many cut-offs in window before we alert
            'cutoff_alert_cooldown_hours'      => 6,    // suppress repeat alerts inside this window
            'cutoff_alert_emails'              => '',   // optional comma-separated extra recipients
            'cutoff_alert_last_sent_at'        => null, // ISO-8601 timestamp written by the checker
            'cutoff_alerting'                  => false, // true while we're in an active alert state; flipped off when the recovery notice is sent
            'cutoff_alert_recovered_at'        => null, // ISO-8601 timestamp of the last recovery notification
            // Optional per-locale overrides for the greeting bubble and
            // starter prompt buttons shown when the chat first opens.
            // Shape: ['fr' => 'Bonjour…', …] for greeting,
            // and:   ['fr' => ['Que puis-je faire ?', …], …] for prompts.
            // Empty/missing entries fall back to the default English copy.
            'greeting_locales'        => [],
            'starter_prompts_locales' => [],
            // Optional per-locale overrides for the assistant system
            // prompt that steers the model. Shape: ['fr' => '…', …].
            // Empty/missing entries fall back to the default English
            // `system_prompt` above.
            'system_prompt_locales'   => [],
            // Default + per-locale overrides for the chat textarea
            // placeholder and Send button label. Empty default falls
            // back to the built-in English copy ("Type a message…" /
            // "Send"). Per-locale overrides resolve via the visitor's
            // Accept-Language header, same as the greeting/system
            // prompt locales above.
            'input_placeholder'         => '',
            'input_placeholder_locales' => [],
            'send_label'                => '',
            'send_label_locales'        => [],
        ];
    }

    public const DEFAULT_INPUT_PLACEHOLDER = 'Type a message…';
    public const DEFAULT_SEND_LABEL        = 'Send';

    public static function defaultSystemPrompt(): string
    {
        return <<<'P'
You are the 1INME Assistant, a helpful, concise guide that lives on every
page of the 1INME web app. Visitors may be browsing marketing pages or
signed in to their dashboard. Always:

- Use the "Page context" block to ground your answer in what the user
  is currently looking at, and suggest the most relevant next action.
- When grounded knowledge is provided in the "Knowledge" block, prefer
  it over your prior knowledge. If the answer isn't in there and isn't
  general 1INME knowledge, say so politely and offer to connect with
  the support team.
- Keep replies short (under ~120 words) and friendly. Use short
  paragraphs or compact bullet lists.
- Never invent prices, plan limits, URLs, or user data. If unsure, say
  so and suggest contacting support.
- If the user asks to speak with a human, offer the "Talk to a human"
  action.
P;
    }

    public static function get(): array
    {
        $stored = AppSetting::get(self::KEY, []);
        if (!is_array($stored)) $stored = [];
        return array_replace(self::defaults(), $stored);
    }

    public static function update(array $data): array
    {
        $current = self::get();
        $merged = array_replace($current, $data);
        AppSetting::put(self::KEY, $merged);
        return $merged;
    }

    public static function isEnabledFor(string $surface): bool
    {
        $cfg = self::get();
        if ($surface === 'marketing') return (bool) $cfg['enabled_marketing'];
        if ($surface === 'app')       return (bool) $cfg['enabled_app'];
        return false;
    }

    /**
     * Total credits charged to the assistant in the current calendar
     * month — covers BOTH chat completions and embeddings/retrieval as
     * long as those calls were tagged with feature='site_assistant'.
     * Sums absolute spend (debits are negative deltas in the ledger).
     */
    public static function monthlySpend(): int
    {
        try {
            $sum = (int) \DB::table('ai_credit_transactions')
                ->where('feature', 'site_assistant')
                ->where('type', 'spend')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum(\DB::raw('ABS(delta_credits)'));
            if ($sum > 0) return $sum;
        } catch (\Throwable $e) {
            // table may be missing in early test envs — fall back below
        }
        return (int) \App\Modules\Common\Models\SiteAssistantMessage::where(
            'created_at', '>=', now()->startOfMonth()
        )->sum('credits_spent');
    }

    /**
     * Resolve (and lazily create) the dedicated platform Mind that
     * stores admin-curated knowledge for the Site Assistant. Sources
     * added under "Knowledge Sources" land here so the runtime can
     * always include them in retrieval, even when no platform Mind has
     * been pinned via `mind_ids`.
     */
    public static function ensureAssistantMind(): \App\Modules\User\Models\AiMind
    {
        $cfg = self::get();
        $id  = (int) ($cfg['assistant_mind_id'] ?? 0);
        if ($id > 0) {
            $mind = \App\Modules\User\Models\AiMind::query()->whereNull('user_id')->find($id);
            if ($mind) return $mind;
        }
        $mind = \App\Modules\User\Models\AiMind::query()
            ->whereNull('user_id')
            ->where('name', 'Site Assistant Knowledge')
            ->first();
        if (!$mind) {
            $mind = \App\Modules\User\Models\AiMind::create([
                'user_id'     => null,
                'name'        => 'Site Assistant Knowledge',
                'description' => 'Admin-curated URLs and pasted content for the site-wide AI assistant. Sources can be scoped to specific marketing pages.',
                'is_default'  => false,
                'is_disabled' => false,
            ]);
        }
        self::update(['assistant_mind_id' => (int) $mind->id]);
        return $mind;
    }

    /**
     * Normalize the per-locale low-balance message overrides posted from
     * the admin form. Locale codes are canonicalised to BCP-47 form
     * (`fr`, `pt-BR`); malformed codes are dropped silently. Empty
     * `signed_in`/`anonymous` values are stripped so they fall back to
     * the default copy at render time. Capped at 50 entries to keep
     * the settings blob small.
     */
    public static function normalizeLowBalanceLocales(array $in): array
    {
        $out = [];
        foreach ($in as $code => $row) {
            if (!is_array($row)) continue;
            $canon = \App\Modules\Common\Support\CookieConsentConfig::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $entry = [];
            foreach (['signed_in', 'anonymous'] as $k) {
                if (!array_key_exists($k, $row)) continue;
                $val = trim((string) $row[$k]);
                if ($val === '') continue;
                $entry[$k] = mb_substr($val, 0, 500);
            }
            if (!empty($entry)) $out[$canon] = $entry;
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Resolve the locale-specific low-balance message for the given
     * audience (`signed_in` or `anonymous`) using the visitor's
     * Accept-Language header. Falls back to the default English copy
     * stored in `low_balance_message_<audience>` when no locale override
     * matches. When $acceptLanguage is null, the current request's
     * header is used.
     */
    public static function lowBalanceMessageFor(array $cfg, string $audience, ?string $acceptLanguage = null): string
    {
        $defaultKey = $audience === 'signed_in'
            ? 'low_balance_message_signed_in'
            : 'low_balance_message_anonymous';
        $default = trim((string) ($cfg[$defaultKey] ?? ''));

        $locales = (array) ($cfg['low_balance_message_locales'] ?? []);
        if (empty($locales)) return $default;

        $acceptLanguage = self::resolveAcceptLanguage($acceptLanguage);
        if (!$acceptLanguage) return $default;

        $picked = \App\Modules\Common\Support\CookieConsentConfig::pickLocale(array_keys($locales), $acceptLanguage);
        if ($picked === null) return $default;

        $override = trim((string) ($locales[$picked][$audience] ?? ''));
        return $override !== '' ? $override : $default;
    }

    /**
     * Normalize the per-locale CTA button label overrides posted from
     * the admin form. Same shape rules as the greeting locales: BCP-47
     * canonicalised codes, blanks dropped, capped at 50 entries, each
     * label capped at 60 chars to keep the bubble layout tidy.
     */
    public static function normalizeTopupLabelLocales(array $in): array
    {
        $out = [];
        foreach ($in as $code => $val) {
            $canon = \App\Modules\Common\Support\CookieConsentConfig::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $val = trim((string) $val);
            if ($val === '') continue;
            $out[$canon] = mb_substr($val, 0, 60);
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Resolve the locale-specific CTA button label using the visitor's
     * Accept-Language header. Falls back to the admin-configured
     * default (`low_balance_topup_label`) when no locale override
     * matches; returns an empty string when the admin hasn't set a
     * default either, signalling callers to use the audience-specific
     * built-in label (`Top up` / `See plans`).
     */
    public static function topupLabelFor(array $cfg, ?string $acceptLanguage = null): string
    {
        $default = trim((string) ($cfg['low_balance_topup_label'] ?? ''));

        $locales = (array) ($cfg['low_balance_topup_label_locales'] ?? []);
        if (empty($locales)) return $default;

        $accept = self::resolveAcceptLanguage($acceptLanguage);
        if (!$accept) return $default;

        $picked = \App\Modules\Common\Support\CookieConsentConfig::pickLocale(array_keys($locales), $accept);
        if ($picked === null) return $default;

        $val = trim((string) ($locales[$picked] ?? ''));
        return $val !== '' ? $val : $default;
    }

    /**
     * Normalize the per-locale greeting overrides posted from the admin
     * form. Same shape rules as {@see normalizeLowBalanceLocales()}:
     * BCP-47 canonicalised codes, blanks dropped, capped at 50 entries.
     */
    public static function normalizeGreetingLocales(array $in): array
    {
        $out = [];
        foreach ($in as $code => $val) {
            $canon = \App\Modules\Common\Support\CookieConsentConfig::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $val = trim((string) $val);
            if ($val === '') continue;
            $out[$canon] = mb_substr($val, 0, 500);
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Normalize the per-locale starter prompt overrides. Each entry is
     * an ordered list of short prompt strings; blank entries are
     * dropped and each prompt is capped at 200 chars (matching the
     * default copy field length). Locales with no surviving prompts
     * are omitted so they fall back to the default English set.
     */
    public static function normalizeStarterPromptsLocales(array $in): array
    {
        $out = [];
        foreach ($in as $code => $list) {
            if (!is_array($list)) continue;
            $canon = \App\Modules\Common\Support\CookieConsentConfig::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $clean = [];
            foreach ($list as $p) {
                $p = trim((string) $p);
                if ($p === '') continue;
                $clean[] = mb_substr($p, 0, 200);
                if (count($clean) >= 10) break;
            }
            if (!empty($clean)) $out[$canon] = $clean;
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Normalize the per-locale system prompt overrides posted from the
     * admin form. BCP-47 canonicalised codes; blanks dropped; capped
     * at 50 entries; each prompt capped at 8000 chars to mirror the
     * default `system_prompt` field length.
     */
    public static function normalizeSystemPromptLocales(array $in): array
    {
        $out = [];
        foreach ($in as $code => $val) {
            $canon = \App\Modules\Common\Support\CookieConsentConfig::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $val = trim((string) $val);
            if ($val === '') continue;
            $out[$canon] = mb_substr($val, 0, 8000);
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Resolve the locale-specific system prompt using the visitor's
     * Accept-Language header, falling back to the default English copy
     * (`system_prompt`) when no locale override matches.
     */
    public static function systemPromptFor(array $cfg, ?string $acceptLanguage = null): string
    {
        $default = (string) ($cfg['system_prompt'] ?? '');
        $locales = (array) ($cfg['system_prompt_locales'] ?? []);
        if (empty($locales)) return $default;

        $accept = self::resolveAcceptLanguage($acceptLanguage);
        if (!$accept) return $default;

        $picked = \App\Modules\Common\Support\CookieConsentConfig::pickLocale(array_keys($locales), $accept);
        if ($picked === null) return $default;

        $val = trim((string) ($locales[$picked] ?? ''));
        return $val !== '' ? $val : $default;
    }

    /**
     * Resolve the locale-specific greeting using the visitor's
     * Accept-Language header, falling back to the default English copy
     * (`greeting`) when no locale override matches.
     */
    public static function greetingFor(array $cfg, ?string $acceptLanguage = null): string
    {
        $default = (string) ($cfg['greeting'] ?? '');
        $locales = (array) ($cfg['greeting_locales'] ?? []);
        if (empty($locales)) return $default;

        $accept = self::resolveAcceptLanguage($acceptLanguage);
        if (!$accept) return $default;

        $picked = \App\Modules\Common\Support\CookieConsentConfig::pickLocale(array_keys($locales), $accept);
        if ($picked === null) return $default;

        $val = trim((string) ($locales[$picked] ?? ''));
        return $val !== '' ? $val : $default;
    }

    /**
     * Resolve the locale-specific starter prompts using the visitor's
     * Accept-Language header, falling back to the default English set
     * (`starter_prompts`) when no locale override matches or the
     * matched locale's list is empty.
     *
     * @return array<int,string>
     */
    public static function starterPromptsFor(array $cfg, ?string $acceptLanguage = null): array
    {
        $default = array_values((array) ($cfg['starter_prompts'] ?? []));
        $locales = (array) ($cfg['starter_prompts_locales'] ?? []);
        if (empty($locales)) return $default;

        $accept = self::resolveAcceptLanguage($acceptLanguage);
        if (!$accept) return $default;

        $picked = \App\Modules\Common\Support\CookieConsentConfig::pickLocale(array_keys($locales), $accept);
        if ($picked === null) return $default;

        $list = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s),
            (array) ($locales[$picked] ?? [])
        )));
        return !empty($list) ? $list : $default;
    }

    /**
     * Normalize the per-locale overrides for the chat input placeholder.
     * Same shape rules as {@see normalizeTopupLabelLocales()}: BCP-47
     * canonicalised codes, blanks dropped, capped at 50 entries, each
     * value capped at 120 chars to keep the input chrome tidy.
     */
    public static function normalizeInputPlaceholderLocales(array $in): array
    {
        return self::normalizeShortStringLocales($in, 120);
    }

    /**
     * Normalize the per-locale overrides for the Send button label.
     * Capped at 40 chars so the button doesn't blow out the input row.
     */
    public static function normalizeSendLabelLocales(array $in): array
    {
        return self::normalizeShortStringLocales($in, 40);
    }

    /**
     * Shared helper: normalize a flat per-locale string map. Used by
     * the input placeholder and Send label locale fields, which both
     * have the same `[locale => string]` shape.
     */
    protected static function normalizeShortStringLocales(array $in, int $maxLen): array
    {
        $out = [];
        foreach ($in as $code => $val) {
            $canon = \App\Modules\Common\Support\CookieConsentConfig::canonicalLocale((string) $code);
            if ($canon === null) continue;
            $val = trim((string) $val);
            if ($val === '') continue;
            $out[$canon] = mb_substr($val, 0, $maxLen);
            if (count($out) >= 50) break;
        }
        ksort($out);
        return $out;
    }

    /**
     * Resolve the locale-specific input placeholder using the visitor's
     * Accept-Language header. Falls back to the admin-configured
     * default (`input_placeholder`) and finally to the built-in English
     * copy (`Type a message…`) so the widget never renders blank.
     */
    public static function inputPlaceholderFor(array $cfg, ?string $acceptLanguage = null): string
    {
        return self::resolveLocalizedShortString(
            $cfg,
            'input_placeholder',
            'input_placeholder_locales',
            self::DEFAULT_INPUT_PLACEHOLDER,
            $acceptLanguage
        );
    }

    /**
     * Resolve the locale-specific Send button label. Same fallback
     * chain as {@see inputPlaceholderFor()} — admin default, then the
     * built-in English copy (`Send`).
     */
    public static function sendLabelFor(array $cfg, ?string $acceptLanguage = null): string
    {
        return self::resolveLocalizedShortString(
            $cfg,
            'send_label',
            'send_label_locales',
            self::DEFAULT_SEND_LABEL,
            $acceptLanguage
        );
    }

    /**
     * Shared resolver for "[locale => string]" fields that also have a
     * scalar default. Matches the visitor's Accept-Language header
     * against the override keys; falls back to the admin default; then
     * to the built-in English copy.
     */
    protected static function resolveLocalizedShortString(array $cfg, string $defaultKey, string $localesKey, string $builtin, ?string $acceptLanguage): string
    {
        $default = trim((string) ($cfg[$defaultKey] ?? ''));
        if ($default === '') $default = $builtin;

        $locales = (array) ($cfg[$localesKey] ?? []);
        if (empty($locales)) return $default;

        $accept = self::resolveAcceptLanguage($acceptLanguage);
        if (!$accept) return $default;

        $picked = \App\Modules\Common\Support\CookieConsentConfig::pickLocale(array_keys($locales), $accept);
        if ($picked === null) return $default;

        $val = trim((string) ($locales[$picked] ?? ''));
        return $val !== '' ? $val : $default;
    }

    /**
     * Read the visitor's Accept-Language header from the current
     * request when an explicit value isn't provided. Returns an empty
     * string if no request context is available.
     */
    protected static function resolveAcceptLanguage(?string $acceptLanguage): string
    {
        if ($acceptLanguage !== null) return $acceptLanguage;
        if (function_exists('request')) {
            try {
                return (string) (request()->server('HTTP_ACCEPT_LANGUAGE') ?? '');
            } catch (\Throwable $e) {
                // fall through to empty
            }
        }
        return '';
    }

    public static function isOverBudget(): bool
    {
        $cfg = self::get();
        $cap = (int) ($cfg['monthly_budget_credits'] ?? 0);
        if ($cap <= 0) return false;
        return self::monthlySpend() >= $cap;
    }
}
