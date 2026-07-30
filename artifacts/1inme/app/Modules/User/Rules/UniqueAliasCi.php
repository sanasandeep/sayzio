<?php

namespace App\Modules\User\Rules;

use App\Modules\User\Support\AliasNamespace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Case-insensitive, PER-DOMAIN uniqueness rule for a link's custom URL
 * (alias).
 *
 * Aliases are unique within a domain namespace, not globally across all
 * platform domains: `sayzio.app/sana` and `1in.me/sana` may belong to two
 * different links. Pass the domain the alias is being bound to via
 * `$domainId` (a raw request value is fine — null/'' means the default
 * platform domain, whose namespace also covers legacy NULL rows).
 *
 * Checks BOTH the primary `links.alias` column and the additional
 * `link_aliases.alias` table so a value already serving as an extra alias in
 * the same namespace is rejected too.
 *
 * Pass the current link id to `$ignoreLinkId` on edit screens so an unchanged
 * alias (or an extra alias owned by the same link, which gets demoted or
 * absorbed) doesn't report as taken against its own rows.
 */
class UniqueAliasCi implements ValidationRule
{
    public function __construct(
        private ?int $ignoreLinkId = null,
        private int|string|null $domainId = null,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return;
        }

        if (AliasNamespace::isTaken($normalized, $this->domainId, $this->ignoreLinkId)) {
            $fail('This URL is already taken — try another.');
        }
    }
}
