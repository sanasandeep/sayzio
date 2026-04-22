<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AppSetting;

/**
 * Admin caps + tunables for AI Personas. All knobs live in the shared
 * `app_settings` key/value store so an operator can raise / lower
 * limits without a code change.
 *
 * Stored keys:
 *   ai.persona.caps  array — see capsDefault().
 */
class PersonaSettings
{
    public const KEY_CAPS = 'ai.persona.caps';

    public static function capsDefault(): array
    {
        return [
            'max_personas_per_user'     => 10,
            'max_minds_per_persona'     => 8,
            'max_versions_per_persona'  => 50,
            'max_starter_questions'     => 6,
            'max_system_prompt_chars'   => 6000,
            'max_style_guide_chars'     => 2000,
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
