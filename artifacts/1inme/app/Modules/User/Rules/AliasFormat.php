<?php

namespace App\Modules\User\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule for a link's custom URL (alias) character set.
 *
 * Allows letters, numbers, dots (`.`), underscores (`_`) and dashes (`-`),
 * matching the REST API's existing regex. This is the single source of truth
 * for the alias character rule across the web create flow, the per-type link
 * creators, the biolink/conversational wizard, the live availability checker,
 * the bulk-creation path and the REST API — so every entry point accepts the
 * same set (notably the dot, which the old `alpha_dash` rule rejected).
 *
 * Letters are accepted in any case; case-insensitive uniqueness/resolution is
 * handled separately (see UniqueAliasCi and Link::resolveByAlias).
 */
class AliasFormat implements ValidationRule
{
    /** Allowed character set for a custom URL. */
    public const REGEX = '/^[A-Za-z0-9._-]+$/';

    /** User-facing message naming every allowed character (including the dot). */
    public const MESSAGE = 'Only letters, numbers, dots, dashes & underscores are allowed.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! preg_match(self::REGEX, $value)) {
            $fail(self::MESSAGE);
        }
    }
}
