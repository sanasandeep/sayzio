<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Picks the ONE plan a visitor sees from each plan category.
 *
 * A category is a bucket of near-identical plans — same shape, small
 * differences in name, price and minor features. The public page shows one of
 * them and the visitor never learns the siblings exist.
 *
 * ---- Why this runs on the server ------------------------------------
 * /pricing embeds its whole catalogue as JSON for the client-side matcher.
 * If the variants were filtered in the browser, every sibling price would sit
 * in the page source, and "the visitor sees one plan" would be true only for
 * people who do not open developer tools. So the collection is reduced HERE,
 * before anything is handed to the view, and the embedded catalogue contains
 * representatives only.
 *
 * ---- Which variant -------------------------------------------------
 *  1. the pin this visitor already has for that category, if any
 *  2. the variant flagged `is_category_default`
 *  3. the lowest `sort_order`, then lowest id — a stable answer rather than
 *     whatever the database happens to return first
 *
 * Step 1 is what makes the choice permanent. Changing a category's default
 * moves NEW visitors only; a person who has already been quoted a price keeps
 * it. That is the difference between a price test and a page that quietly
 * charges people differently depending on when they looked.
 *
 * Pins are written for the visible set on each render, once, and never
 * rewritten.
 */
class PlanVariantResolver
{
    /** Cookie carrying the logged-out visitor's identity. Two years. */
    public const COOKIE = 'sayzio_pv';
    private const COOKIE_MINUTES = 60 * 24 * 730;

    private readonly PlanSetRoller $roller;

    private readonly int $roll;

    public function __construct(private readonly Request $request)
    {
        $this->roller = new PlanSetRoller($request);
        $this->roll = $this->roller->current();
    }

    /**
     * Reduce a plan collection to one plan per category, in the order the
     * categories first appeared.
     *
     * @param  Collection<int, Plan>  $plans
     * @return Collection<int, Plan>
     */
    public function representatives(Collection $plans): Collection
    {
        if ($plans->isEmpty()) {
            return $plans;
        }

        $byCategory = $plans->groupBy(fn (Plan $p) => $p->categoryKey());

        // Categories with a single member are the overwhelming majority (every
        // plan that has never been given a category is its own). They need no
        // pin and no lookup, so only ask the database about the real ones.
        $contested = $byCategory->filter(fn (Collection $variants) => $variants->count() > 1);

        if ($contested->isEmpty()) {
            return $byCategory->map(fn (Collection $v) => $v->first())->values();
        }

        $pins = $this->pinsFor($contested->keys()->all());
        $toPin = [];

        $chosen = $byCategory->map(function (Collection $variants, string $category) use ($pins, &$toPin) {
            if ($variants->count() === 1) {
                return $variants->first();
            }

            // An explicit "show me another set" moves the visitor to a new
            // roll, and the roll decides the variant from then on. The pin
            // still records what they were shown, and still governs a visitor
            // who has never asked for another set, so a passive page view
            // never re-prices anyone.
            if ($this->roll > 0) {
                $ordered = $variants->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
                $pick = $ordered[$this->roller->variantIndex($category, $ordered->count(), $this->roll)]
                    ?? $ordered->first();
                $toPin[$category] = $pick->id;

                return $pick;
            }

            $pinnedId = $pins[$category] ?? null;
            if ($pinnedId !== null) {
                $pinned = $variants->firstWhere('id', $pinnedId);
                if ($pinned) {
                    return $pinned;
                }
                // The pinned variant has been archived or recategorised. Fall
                // through and re-pin, rather than showing nothing.
            }

            $pick = $variants->firstWhere('is_category_default', true)
                ?? $variants->sortBy([['sort_order', 'asc'], ['id', 'asc']])->first();

            $toPin[$category] = $pick->id;

            return $pick;
        });

        if ($toPin) {
            $this->writePins($toPin);
        }

        return $chosen->values();
    }

    /**
     * @param  list<string>  $categories
     * @return array<string, int>
     */
    private function pinsFor(array $categories): array
    {
        $userId = $this->request->user('web')?->id;
        $token = $this->token(false);

        if (!$userId && !$token) {
            return [];
        }

        $rows = DB::table('plan_variant_pins')
            ->whereIn('plan_category', $categories)
            ->where(function ($q) use ($userId, $token) {
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
                if ($token) {
                    $q->orWhere('visitor_token', $token);
                }
            })
            ->get(['plan_category', 'plan_id', 'user_id']);

        $out = [];
        foreach ($rows as $row) {
            // A signed-in pin wins over the browser's, so moving to a new
            // device does not re-roll a price the account already has.
            if (isset($out[$row->plan_category]) && $row->user_id === null) {
                continue;
            }
            $out[$row->plan_category] = (int) $row->plan_id;
        }

        return $out;
    }

    /** @param array<string, int> $picks */
    private function writePins(array $picks): void
    {
        $userId = $this->request->user('web')?->id;
        $token = $userId ? $this->token(false) : $this->token(true);

        if (!$userId && !$token) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($picks as $category => $planId) {
            $rows[] = [
                'user_id' => $userId,
                'visitor_token' => $userId ? null : $token,
                'plan_category' => $category,
                'plan_id' => $planId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // On roll 0 a pin is written once and is not ours to change: a
        // concurrent request that got there first has already quoted the
        // visitor a price. Past roll 0 the visitor has explicitly asked for a
        // different set, so the record is updated to what they are now seeing.
        if ($this->roll > 0) {
            DB::table('plan_variant_pins')->upsert(
                $rows,
                $userId ? ['user_id', 'plan_category'] : ['visitor_token', 'plan_category'],
                ['plan_id', 'updated_at'],
            );

            return;
        }

        DB::table('plan_variant_pins')->insertOrIgnore($rows);
    }

    /**
     * The logged-out visitor's token. Minted only when there is something to
     * remember, so an ordinary visit to a site with no categories sets no
     * cookie at all.
     */
    private function token(bool $mint): ?string
    {
        $existing = $this->request->cookie(self::COOKIE);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        if (!$mint) {
            return null;
        }

        $token = (string) Str::uuid();

        // Put it on THIS request as well as the response. A queued cookie is
        // only visible to the browser on the next request, so without this a
        // second resolve inside the same request finds no token, finds no pin,
        // and silently re-rolls the variant — which is the one thing a pin
        // exists to prevent.
        $this->request->cookies->set(self::COOKIE, $token);

        Cookie::queue(Cookie::make(
            self::COOKIE,
            $token,
            self::COOKIE_MINUTES,
            null,
            null,
            null,
            true,   // httpOnly: nothing in the page needs to read it
            false,
            'lax',
        ));

        return $token;
    }

    /**
     * Hand a logged-out visitor's pins to the account they just created, so
     * the price they were quoted before signing up is the price they keep.
     * Safe to call more than once.
     */
    public static function claimForUser(int $userId, ?string $visitorToken): void
    {
        if (!$visitorToken) {
            return;
        }

        $held = DB::table('plan_variant_pins')
            ->where('user_id', $userId)
            ->pluck('plan_category')
            ->all();

        DB::table('plan_variant_pins')
            ->where('visitor_token', $visitorToken)
            ->when($held, fn ($q) => $q->whereNotIn('plan_category', $held))
            ->update(['user_id' => $userId, 'visitor_token' => null, 'updated_at' => now()]);
    }
}
