<?php

namespace App\Modules\User\Services\Contacts;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Finds groups of likely-duplicate contacts for one user.
 *
 * Three matching strategies run independently and their results are merged
 * into deduped groups:
 *
 *  1. Shared normalised phone  — two contacts share a phone in value_e164
 *     (or the stripped value when e164 is absent).
 *  2. Shared normalised email  — two contacts share a lowercase email.
 *  3. Identical display name   — same LOWER(TRIM(display_name)).
 *
 * Pairs dismissed by the user (stored in contact_dismissed_pairs) are
 * excluded before results are returned so they never re-surface.
 *
 * Performance: queries do set-based work in Postgres; no PHP-side loops
 * over the full contact list.
 */
class ContactDuplicateDetector
{
    /** Maximum groups returned in a single call. */
    public const MAX_GROUPS = 100;

    /** How long the per-user group count may be served from cache. */
    public const COUNT_CACHE_TTL_SECONDS = 600;

    /** Cache key for a user's duplicate-group count. */
    public static function countCacheKey(int $userId): string
    {
        return "contacts:dup-group-count:{$userId}";
    }

    /**
     * Invalidate the cached duplicate-group count for a user. Called whenever
     * a contact (or one of its phones/emails) is created, edited or deleted,
     * and when duplicate pairs are dismissed/merged, so the badge on the
     * contacts index reflects the new state on the very next read.
     */
    public static function flushCountCache(int $userId): void
    {
        Cache::forget(self::countCacheKey($userId));
    }

    /**
     * Return groups of likely-duplicate contact IDs for the user.
     *
     * @return array<int, array{ids: int[], reason: string, contacts: array[]}>
     *   Each element is a group with the matching contact IDs (at least 2),
     *   a human-readable reason string, and an empty `contacts` array that
     *   the caller fills in after loading models.
     */
    public function detect(int $userId): array
    {
        $dismissed = $this->dismissedPairs($userId);

        $pairs = collect();

        $pairs = $pairs->merge($this->phonePairs($userId));
        $pairs = $pairs->merge($this->emailPairs($userId));
        $pairs = $pairs->merge($this->namePairs($userId));

        // Deduplicate: canonical pair key = "min_id:max_id"
        $seen = [];
        $filtered = [];
        foreach ($pairs as $pair) {
            $a = min($pair['a'], $pair['b']);
            $b = max($pair['a'], $pair['b']);
            $key = "{$a}:{$b}";
            if (isset($dismissed[$key])) continue;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $filtered[] = ['a' => $a, 'b' => $b, 'reason' => $pair['reason']];
            }
        }

        // Union-find: merge overlapping pairs into groups
        $groups = $this->unionFind($filtered);

        return array_slice(
            array_values(array_filter($groups, fn ($g) => count($g['ids']) >= 2)),
            0,
            self::MAX_GROUPS
        );
    }

    /**
     * Total count of undismissed duplicate groups for a user, without loading
     * contact models. Used for the badge / banner on the contacts index.
     */
    public function count(int $userId): int
    {
        // Cached so the index/import-summary badge is cheap; write paths
        // (contact edits, merges, dismissals) call flushCountCache() so the
        // number is recomputed immediately after anything that can change it.
        return (int) Cache::remember(
            self::countCacheKey($userId),
            self::COUNT_CACHE_TTL_SECONDS,
            fn () => count($this->detect($userId))
        );
    }

    /**
     * Cheap targeted check: does this one contact currently match at least
     * one OTHER contact of the same user (shared phone / email / identical
     * name), ignoring pairs the user already dismissed?
     *
     * Used on the save path (store/update) to surface an inline "possible
     * duplicate" notice without running the full detect() scan — each query
     * is anchored on the single contact's own values so it stays fast even
     * for large address books.
     */
    public function contactHasDuplicate(int $userId, int $contactId): bool
    {
        $dismissed = $this->dismissedPairs($userId);

        $others = array_merge(
            $this->phoneMatchesFor($userId, $contactId),
            $this->emailMatchesFor($userId, $contactId),
            $this->nameMatchesFor($userId, $contactId)
        );

        foreach ($others as $otherId) {
            $a = min($contactId, $otherId);
            $b = max($contactId, $otherId);
            if (!isset($dismissed["{$a}:{$b}"])) {
                return true;
            }
        }

        return false;
    }

    /** Other contact IDs sharing a normalised phone with the given contact. */
    protected function phoneMatchesFor(int $userId, int $contactId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT b.contact_id AS id
            FROM   contact_phones a
            JOIN   contact_phones b ON (
                       COALESCE(NULLIF(a.value_e164,''), regexp_replace(a.value,'[^0-9]','','g'))
                     = COALESCE(NULLIF(b.value_e164,''), regexp_replace(b.value,'[^0-9]','','g'))
                   )
            JOIN   contacts cb ON cb.id = b.contact_id AND cb.user_id = ?
            WHERE  a.contact_id = ?
              AND  b.contact_id <> a.contact_id
              AND  COALESCE(NULLIF(a.value_e164,''), regexp_replace(a.value,'[^0-9]','','g')) <> ''
        SQL;

        return array_map(fn ($r) => (int) $r->id, DB::select($sql, [$userId, $contactId]));
    }

    /** Other contact IDs sharing a normalised email with the given contact. */
    protected function emailMatchesFor(int $userId, int $contactId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT b.contact_id AS id
            FROM   contact_emails a
            JOIN   contact_emails b ON LOWER(TRIM(a.value)) = LOWER(TRIM(b.value))
            JOIN   contacts cb ON cb.id = b.contact_id AND cb.user_id = ?
            WHERE  a.contact_id = ?
              AND  b.contact_id <> a.contact_id
              AND  TRIM(a.value) <> ''
        SQL;

        return array_map(fn ($r) => (int) $r->id, DB::select($sql, [$userId, $contactId]));
    }

    /** Other contact IDs with an identical normalised display_name. */
    protected function nameMatchesFor(int $userId, int $contactId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT b.id
            FROM   contacts a
            JOIN   contacts b
                ON  LOWER(TRIM(COALESCE(a.display_name,''))) = LOWER(TRIM(COALESCE(b.display_name,'')))
               AND  b.id <> a.id
               AND  b.user_id = ?
            WHERE  a.id = ?
              AND  TRIM(COALESCE(a.display_name,'')) <> ''
        SQL;

        return array_map(fn ($r) => (int) $r->id, DB::select($sql, [$userId, $contactId]));
    }

    // ------------------------------------------------------------------
    //  Internal helpers
    // ------------------------------------------------------------------

    /** Canonical dismissed-pair lookup: "min_id:max_id" => true. */
    protected function dismissedPairs(int $userId): array
    {
        $rows = DB::table('contact_dismissed_pairs')
            ->where('user_id', $userId)
            ->get(['contact_id_a', 'contact_id_b']);

        $map = [];
        foreach ($rows as $r) {
            $a = min($r->contact_id_a, $r->contact_id_b);
            $b = max($r->contact_id_a, $r->contact_id_b);
            $map["{$a}:{$b}"] = true;
        }
        return $map;
    }

    /** Pairs sharing a normalised phone. */
    protected function phonePairs(int $userId): array
    {
        // Use value_e164 when present, fall back to regexp-stripped value.
        $sql = <<<'SQL'
            SELECT a.contact_id AS a, b.contact_id AS b
            FROM   contact_phones a
            JOIN   contacts ca ON ca.id = a.contact_id AND ca.user_id = ?
            JOIN   contact_phones b ON (
                       COALESCE(NULLIF(a.value_e164,''), regexp_replace(a.value,'[^0-9]','','g'))
                     = COALESCE(NULLIF(b.value_e164,''), regexp_replace(b.value,'[^0-9]','','g'))
                   )
            JOIN   contacts cb ON cb.id = b.contact_id AND cb.user_id = ?
            WHERE  a.contact_id <> b.contact_id
              AND  COALESCE(NULLIF(a.value_e164,''), regexp_replace(a.value,'[^0-9]','','g')) <> ''
            GROUP  BY a.contact_id, b.contact_id
        SQL;

        $rows = DB::select($sql, [$userId, $userId]);
        $pairs = [];
        foreach ($rows as $r) {
            $pairs[] = ['a' => (int) $r->a, 'b' => (int) $r->b, 'reason' => 'Shared phone number'];
        }
        return $pairs;
    }

    /** Pairs sharing a normalised (lowercased) email. */
    protected function emailPairs(int $userId): array
    {
        $sql = <<<'SQL'
            SELECT a.contact_id AS a, b.contact_id AS b
            FROM   contact_emails a
            JOIN   contacts ca ON ca.id = a.contact_id AND ca.user_id = ?
            JOIN   contact_emails b ON LOWER(TRIM(a.value)) = LOWER(TRIM(b.value))
            JOIN   contacts cb ON cb.id = b.contact_id AND cb.user_id = ?
            WHERE  a.contact_id <> b.contact_id
              AND  TRIM(a.value) <> ''
            GROUP  BY a.contact_id, b.contact_id
        SQL;

        $rows = DB::select($sql, [$userId, $userId]);
        $pairs = [];
        foreach ($rows as $r) {
            $pairs[] = ['a' => (int) $r->a, 'b' => (int) $r->b, 'reason' => 'Shared email address'];
        }
        return $pairs;
    }

    /**
     * Pairs with identical normalised display_name (and both non-empty).
     * Uses pg_trgm similarity (already indexed on the contacts table) for
     * near-identical names; falls back to exact-match when trgm is not
     * available.
     */
    protected function namePairs(int $userId): array
    {
        // Exact match on lowercased, trimmed display_name (fast, no trgm needed).
        $sql = <<<'SQL'
            SELECT a.id AS a, b.id AS b
            FROM   contacts a
            JOIN   contacts b
                ON  LOWER(TRIM(COALESCE(a.display_name,''))) = LOWER(TRIM(COALESCE(b.display_name,'')))
               AND  a.id <> b.id
               AND  b.user_id = a.user_id
            WHERE  a.user_id = ?
              AND  TRIM(COALESCE(a.display_name,'')) <> ''
            GROUP  BY a.id, b.id
        SQL;

        $rows = DB::select($sql, [$userId]);
        $pairs = [];
        foreach ($rows as $r) {
            $pairs[] = ['a' => (int) $r->a, 'b' => (int) $r->b, 'reason' => 'Identical name'];
        }
        return $pairs;
    }

    /**
     * Union-find algorithm: merges a flat list of {a, b, reason} pairs into
     * groups where all connected members end up in the same group.
     *
     * @param  array<array{a:int,b:int,reason:string}>  $pairs
     * @return array<array{ids:int[],reasons:string[]}>
     */
    protected function unionFind(array $pairs): array
    {
        $parent  = [];
        $reasons = [];  // node => [reason, ...]

        $find = function (int $x) use (&$parent, &$find): int {
            if (!isset($parent[$x])) $parent[$x] = $x;
            if ($parent[$x] !== $x) $parent[$x] = $find($parent[$x]);
            return $parent[$x];
        };

        $union = function (int $x, int $y, string $reason) use (&$parent, &$reasons, &$find): void {
            $rx = $find($x);
            $ry = $find($y);
            $reasons[$rx][] = $reason;
            $reasons[$ry][] = $reason;
            if ($rx !== $ry) {
                $parent[$rx] = $ry;
                $reasons[$ry] = array_values(array_unique(array_merge($reasons[$ry] ?? [], $reasons[$rx] ?? [])));
            }
        };

        foreach ($pairs as $p) {
            $union($p['a'], $p['b'], $p['reason']);
        }

        // Build groups keyed by root
        $groups = [];
        foreach (array_keys($parent) as $node) {
            $root = $find($node);
            $groups[$root]['ids'][]   = $node;
        }
        foreach ($groups as $root => &$g) {
            $g['reasons'] = array_values(array_unique($reasons[$root] ?? []));
            $g['reason']  = implode(', ', $g['reasons']);
            sort($g['ids']);
        }
        unset($g);

        return array_values($groups);
    }
}
