<?php

namespace App\Modules\Common\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Which set of plans a visitor is currently being shown, and how they ask for
 * another one.
 *
 * The whole state is one integer, the ROLL. Everything else is derived from
 * it: which categories appear in the visitor's Free + 3, and which variant
 * stands for each category. So the same roll always renders the same page.
 *
 * That is the property worth protecting. A refresh, a back button, a return
 * visit tomorrow and the same link opened twice all land on the same roll and
 * therefore the same names at the same prices. The numbers move only when the
 * visitor presses "show me another set" — an explicit request for a different
 * option, not a change made behind them. Prices that drift on their own
 * between page views are how a pricing page loses an argument it cannot see.
 *
 * Derivation is a hash of (visitor token, roll, category), so two visitors on
 * the same roll do not see identical sets, and one visitor's sequence does not
 * repeat in an obvious order.
 */
class PlanSetRoller
{
    public const COOKIE = 'sayzio_pset';

    /** Two years, matching the visitor token the derivation is salted with. */
    private const COOKIE_MINUTES = 60 * 24 * 730;

    /** How many PAID plans sit beside the free one on first load. */
    public const SET_SIZE = 3;

    public function __construct(private readonly Request $request) {}

    public function current(): int
    {
        $raw = $this->request->cookie(self::COOKIE);

        return is_numeric($raw) ? max(0, (int) $raw) : 0;
    }

    /**
     * Move to the next set. Queues the cookie and sets it on this request too,
     * so anything rendering later in the same request already sees the new
     * roll rather than the one the visitor just left.
     */
    public function advance(): int
    {
        $next = $this->current() + 1;

        $this->request->cookies->set(self::COOKIE, (string) $next);
        Cookie::queue(Cookie::make(
            self::COOKIE,
            (string) $next,
            self::COOKIE_MINUTES,
            null,
            null,
            null,
            true,
            false,
            'lax',
        ));

        return $next;
    }

    /**
     * Pick the paid categories for this roll.
     *
     * Rolls walk a rotated order rather than re-shuffling from scratch, so a
     * visitor clicking through sees genuinely different combinations instead
     * of the same two categories recurring by chance.
     *
     * @param  list<string>  $categories
     * @return list<string>
     */
    public function categories(array $categories, ?int $roll = null, int $size = self::SET_SIZE): array
    {
        $categories = array_values(array_unique($categories));
        if (count($categories) <= $size) {
            return $categories;
        }

        $roll ??= $this->current();

        // A per-visitor shuffle so two people on roll 0 do not see the same
        // three, then a one-step rotation per roll.
        //
        // One step, not $size: there are usually only four eligible
        // categories for three slots, so a bigger stride would land back on
        // the same subset immediately. The variety a visitor actually notices
        // comes from the variants inside each category (a different name at a
        // slightly different price), not from swapping whole categories in
        // and out, which there are rarely enough of to do well.
        usort($categories, fn (string $a, string $b) => $this->seed($a, 0) <=> $this->seed($b, 0));
        $offset = $roll % count($categories);

        return array_slice(array_merge($categories, $categories), $offset, $size);
    }

    /**
     * Which variant of a category this roll shows. Returns an index into the
     * category's variants, ordered however the caller ordered them.
     */
    public function variantIndex(string $category, int $variantCount, ?int $roll = null): int
    {
        if ($variantCount <= 1) {
            return 0;
        }

        $roll ??= $this->current();

        return (int) (($this->seed($category, $roll) + $roll) % $variantCount);
    }

    /**
     * Stable per-visitor noise. Salted with the visitor token so the sequence
     * differs between people; falls back to the category alone for a visitor
     * with no token yet, which still gives a deterministic first page.
     */
    private function seed(string $category, int $roll): int
    {
        $token = (string) ($this->request->cookie(PlanVariantResolver::COOKIE) ?? '');

        return (int) (crc32($token.'|'.$category.'|'.$roll) % 100003);
    }
}
