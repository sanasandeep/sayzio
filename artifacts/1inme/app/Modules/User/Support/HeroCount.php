<?php

namespace App\Modules\User\Support;

use Countable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * How many of a thing a page is showing, for the stat chips in the page hero.
 *
 * Every chip on a page header has to be true, and the easy way to get it wrong
 * is ->count() on a paginator: that returns the rows on THIS page, so a header
 * over a paginated list reads "15 posts" on page one of eighty. The chip is
 * then quietly wrong for everyone with more than a screenful, which is exactly
 * the people the number matters to.
 *
 * One place to resolve it, so no page has to remember which kind it was handed.
 */
class HeroCount
{
    /**
     * @param  mixed  $items  paginator, collection, array, or null
     */
    public static function of($items): int
    {
        if ($items === null) {
            return 0;
        }

        // A paginator knows its full size; its count() does not.
        if ($items instanceof Paginator) {
            return method_exists($items, 'total') ? (int) $items->total() : $items->count();
        }

        if ($items instanceof Collection || $items instanceof Countable || is_array($items)) {
            return count($items);
        }

        if (is_object($items) && method_exists($items, 'count')) {
            return (int) $items->count();
        }

        return 0;
    }

    /** "1 post" / "4 posts" — chips read badly when they say "1 posts". */
    public static function label($items, string $singular, ?string $plural = null): string
    {
        $n = self::of($items);

        return number_format($n).' '.($n === 1 ? $singular : ($plural ?? $singular.'s'));
    }
}
