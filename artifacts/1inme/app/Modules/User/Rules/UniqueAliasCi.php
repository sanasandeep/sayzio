<?php

namespace App\Modules\User\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive uniqueness rule for a link's custom URL (alias).
 *
 * Replaces the `unique:links,alias` rule everywhere a custom URL can be set so
 * that two aliases differing only by letter case (e.g. `MyLink` vs `mylink`)
 * are treated as the same and the second one is rejected as already taken —
 * without relying on the database collation. Scope mirrors the original rule:
 * only the primary `links.alias` column is checked (extra aliases live in
 * `link_aliases` and are validated by their own controller).
 *
 * Pass the current link id to `$ignoreLinkId` on edit screens so an unchanged
 * alias doesn't report as taken against its own row.
 */
class UniqueAliasCi implements ValidationRule
{
    public function __construct(private ?int $ignoreLinkId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return;
        }

        $query = DB::table('links')->whereRaw('LOWER(alias) = ?', [$normalized]);
        if ($this->ignoreLinkId !== null) {
            $query->where('id', '!=', $this->ignoreLinkId);
        }

        if ($query->exists()) {
            $fail('This URL is already taken — try another.');
        }
    }
}
