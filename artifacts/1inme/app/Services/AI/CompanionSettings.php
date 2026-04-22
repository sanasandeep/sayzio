<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AppSetting;

/**
 * Admin caps + tunables for AI Companions. Stored under the shared
 * `app_settings` key/value store, just like PersonaSettings, so an
 * operator can lift / tighten platform-wide limits without a deploy.
 *
 * Stored keys:
 *   ai.companion.caps  array — see capsDefault().
 */
class CompanionSettings
{
    public const KEY_CAPS = 'ai.companion.caps';

    public static function capsDefault(): array
    {
        return [
            'max_companions_per_user'      => 5,
            'max_allowed_domains'          => 20,
            // Visitor-side rate limit per companion per IP, per minute.
            'visitor_rate_per_minute'      => 12,
            // Hard ceiling enforced by service even if user sets a
            // higher per-companion `hard_cap_per_month`.
            'platform_hard_cap_per_month'  => 50000,
            // Free turns per month per companion, default for new
            // companions. Owners can lower this; admin sets the global
            // ceiling so a generous default doesn't drain operator AI
            // budget if the model is hosted-key.
            'default_free_turns_per_month' => 50,
            // Maximum visitor message length (chars). Bigger messages
            // are truncated with an ellipsis before being sent to the
            // model.
            'max_visitor_message_chars'    => 2000,
        ];
    }

    /** @return array<string,int> */
    public static function caps(): array
    {
        $stored = AppSetting::get(self::KEY_CAPS);
        $defaults = self::capsDefault();
        if (!is_array($stored)) return $defaults;
        $out = [];
        foreach ($defaults as $k => $v) {
            $out[$k] = isset($stored[$k]) ? max(0, (int) $stored[$k]) : $v;
        }
        return $out;
    }

    public static function setCaps(array $caps): void
    {
        $clean = [];
        foreach (self::capsDefault() as $k => $default) {
            $clean[$k] = isset($caps[$k]) ? max(0, (int) $caps[$k]) : $default;
        }
        AppSetting::put(self::KEY_CAPS, $clean);
    }

    public static function cap(string $name): int
    {
        return self::caps()[$name] ?? 0;
    }
}
