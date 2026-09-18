<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Server-side sorting for tables the database paginates.
 *
 * The client-side enhancer (common/partials/enhanced-table) can only sort the
 * rows already in the DOM. On a paginated table that means it sorts ONE PAGE
 * and presents the result as though it were the whole set -- "oldest account"
 * returns the oldest of the fifteen rows you happen to be looking at. Tables
 * that are paginated by the database hand sorting to the query instead, driven
 * by ?sort= and ?dir= in the URL, and the enhancer stands down on them.
 *
 * Callers pass an allow-list mapping the key the view puts in the sort link to
 * the real column(s) to order by. A key that is not in the allow-list is
 * ignored and the default is used, so ?sort= never reaches the query as
 * anything but a column name we chose ourselves.
 */
final class TableSort
{
    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @param  array<string, string|string[]|\Illuminate\Contracts\Database\Query\Builder>  $allowed  sort key => column(s) or subquery
     * @return array{key: string, dir: string}  the resolved sort, for the view
     */
    public static function apply(
        $query,
        Request $request,
        array $allowed,
        string $defaultKey,
        string $defaultDir = 'desc',
        string $tieBreaker = 'id',
    ): array {
        $key = (string) $request->query('sort', '');

        if (! array_key_exists($key, $allowed)) {
            // The default must itself be allow-listed. If a caller names one
            // that is not, fall through to the tiebreaker alone rather than
            // dying on an undefined index in front of the person using the
            // page -- an unsorted list is recoverable, a 500 is not.
            if (! array_key_exists($defaultKey, $allowed)) {
                if ($tieBreaker !== '') {
                    $query->orderBy($tieBreaker, 'desc');
                }

                return ['key' => '', 'dir' => $defaultDir];
            }

            $key = $defaultKey;
            $dir = $defaultDir;
        } else {
            // Anything that is not an explicit "asc" is descending. This keeps
            // a hand-typed or truncated ?dir= from producing a third state.
            $dir = strtolower((string) $request->query('dir', '')) === 'asc' ? 'asc' : 'desc';
        }

        // A value may be one column, several (a name split across two columns),
        // or a subquery builder for "sort by the related row's name". Only a
        // real array is spread; casting an object with (array) would scatter
        // its properties instead of passing it through to orderBy().
        $columns = $allowed[$key] ?? $allowed[$defaultKey];
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            $query->orderBy($column, $dir);
        }

        // Without a unique tiebreaker, paginating a sort on a non-unique column
        // (status, plan, a date with many ties) lets Postgres return rows in a
        // different order for each page, so a row can appear on two pages and
        // another on none. Ordering by the primary key last makes the sequence
        // total, and therefore the pagination stable.
        if ($tieBreaker !== '' && ! in_array($tieBreaker, $columns, true)) {
            $query->orderBy($tieBreaker, 'desc');
        }

        return ['key' => $key, 'dir' => $dir];
    }
}
