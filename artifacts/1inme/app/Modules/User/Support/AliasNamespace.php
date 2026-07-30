<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Domain;
use Illuminate\Support\Facades\DB;

/**
 * Per-domain alias namespace helpers.
 *
 * Aliases are unique (case-insensitively) *within a domain*, not globally
 * across all platform domains: `sayzio.app/sana` and `1in.me/sana` may be two
 * different links. A NULL domain binding (legacy rows plus fresh installs
 * where the default global domain row doesn't exist yet) belongs to the
 * DEFAULT platform domain's namespace, so NULL and the default domain id are
 * always treated as the same bucket.
 */
class AliasNamespace
{
    /**
     * Normalise a raw domain_id input (request value, column value) into the
     * canonical namespace key: the default platform domain's id maps to null
     * so "default namespace" is always represented one way.
     */
    public static function normalizeDomainId(int|string|null $domainId): ?int
    {
        $domainId = ($domainId === null || $domainId === '') ? null : (int) $domainId;
        if ($domainId !== null && $domainId === Domain::defaultPlatformDomainId()) {
            return null;
        }
        return $domainId;
    }

    /**
     * Constrain $query's domain_id column to the namespace of $domainId.
     * Null (or the default platform domain's id) means the default
     * namespace: domain_id IS NULL OR domain_id = default id.
     */
    public static function scope($query, int|string|null $domainId, string $column = 'domain_id'): void
    {
        $domainId = self::normalizeDomainId($domainId);
        if ($domainId !== null) {
            $query->where($column, $domainId);
            return;
        }
        $defaultId = Domain::defaultPlatformDomainId();
        $query->where(function ($q) use ($column, $defaultId) {
            $q->whereNull($column);
            if ($defaultId !== null) {
                $q->orWhere($column, $defaultId);
            }
        });
    }

    /**
     * True when $alias (case-insensitively) is already used within the given
     * domain namespace — as a primary alias (links.alias) or an additional
     * alias (link_aliases.alias).
     *
     * @param int|null $ignoreLinkId  exclude this link's own rows (primary +
     *        extras) — used on edit screens so an unchanged alias, or an
     *        extra being promoted, doesn't read as taken.
     * @param int|null $ignoreAliasId exclude a specific link_aliases row.
     */
    public static function isTaken(
        string $alias,
        int|string|null $domainId,
        ?int $ignoreLinkId = null,
        ?int $ignoreAliasId = null
    ): bool {
        $lower = mb_strtolower(trim($alias));
        if ($lower === '') {
            return false;
        }

        $links = DB::table('links')->whereRaw('LOWER(alias) = ?', [$lower]);
        self::scope($links, $domainId);
        if ($ignoreLinkId !== null) {
            $links->where('id', '!=', $ignoreLinkId);
        }
        if ($links->exists()) {
            return true;
        }

        $extras = DB::table('link_aliases')->whereRaw('LOWER(alias) = ?', [$lower]);
        self::scope($extras, $domainId);
        if ($ignoreLinkId !== null) {
            $extras->where('link_id', '!=', $ignoreLinkId);
        }
        if ($ignoreAliasId !== null) {
            $extras->where('id', '!=', $ignoreAliasId);
        }
        return $extras->exists();
    }
}
