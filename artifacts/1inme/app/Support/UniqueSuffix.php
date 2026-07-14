<?php

namespace App\Support;

/**
 * Resolves the next free "base" / "base-N" value for a column with a
 * SINGLE database query instead of probing one candidate at a time.
 *
 * The old `while (exists(base-N)) N++` pattern is O(collisions) round
 * trips: with hundreds of rows sharing a base slug (e.g. every account
 * minting a same-named default record on sign-up) a single create was
 * issuing hundreds of sequential `exists` queries — minutes over a
 * remote database. Instead we fetch every colliding value in one query
 * (`col = base OR col LIKE base-%`) and compute the next suffix locally.
 */
final class UniqueSuffix
{
    /**
     * @param \Illuminate\Contracts\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder $query
     *        Pre-scoped query (ownership scope, ignore-id constraint, etc.).
     * @param string $base   The desired base value (already slugged/sanitised).
     * @param string $column Column holding the value (default "slug").
     */
    public static function resolve($query, string $base, string $column = 'slug'): string
    {
        // Escape LIKE wildcards so a base containing % or _ can't
        // over-match (slugged input never does, but stay safe).
        $like = addcslashes($base, '%_\\') . '-%';

        $taken = (clone $query)
            ->where(function ($q) use ($base, $like, $column) {
                $q->where($column, $base)->orWhere($column, 'like', $like);
            })
            ->pluck($column)
            ->all();

        if (!in_array($base, $taken, true)) {
            return $base;
        }

        // First numbered variant is "base-2", matching the previous
        // probe-loop behaviour across all call sites.
        $max = 1;
        $prefixLen = strlen($base) + 1;
        foreach ($taken as $value) {
            $suffix = substr((string) $value, $prefixLen);
            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $base . '-' . ($max + 1);
    }
}
