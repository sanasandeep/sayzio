<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * Public `/events` directory (Task #3589) — mirrors `/creators` but lists
 * published `ics` events that have opted into discovery (ticketing_enabled
 * events default to visible; owners can hide any event via
 * settings['hide_from_directory']). Supports a "near me" filter using the
 * event's stored lat/long (Haversine distance in a raw SQL expression).
 */
class EventsDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $q        = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $tag      = mb_strtolower(ltrim(trim((string) $request->query('tag', '')), '#'));
        $lat      = $request->query('lat');
        $lng      = $request->query('lng');
        $radiusKm = max(1, min(500, (int) $request->query('radius', 50)));
        $online   = $request->boolean('online');
        $price    = trim((string) $request->query('price', ''));
        $priceFilter = in_array($price, ['free', 'paid'], true) ? $price : '';
        $dateFilter = trim((string) $request->query('date', ''));
        $dateFrom   = trim((string) $request->query('date_from', ''));
        $dateTo     = trim((string) $request->query('date_to', ''));

        $query = Link::query()
            ->where('type', 'ics')
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->with(['icsData', 'eventTicketTiers' => fn ($t) => $t->where('is_active', true)])
            ->withCount(['eventInterests as interested_count' => function ($w) {
                $w->where('status', 'interested');
            }])
            ->whereHas('icsData', function ($w) {
                $w->where('start_date', '>=', now()->subDay());
            });

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'ilike', $like)
                  ->orWhereHas('icsData', fn ($ics) => $ics->where('location', 'ilike', $like)
                      ->orWhere('description', 'ilike', $like)
                      ->orWhereRaw('hashtags::text ilike ?', [$like]));
            });
        }

        $curatedSlugs = array_keys(\App\Modules\User\Support\EventCategories::all());

        if ($category === \App\Modules\User\Support\EventCategories::OTHER) {
            // "Other" pill: every stored value that isn't a curated slug.
            $placeholders = implode(',', array_fill(0, count($curatedSlugs), '?'));
            $query->whereRaw("settings->>'event_category' IS NOT NULL")
                  ->whereRaw("settings->>'event_category' NOT IN ($placeholders)", $curatedSlugs);
        } elseif ($category !== '') {
            $query->whereRaw("settings->>'event_category' = ?", [$category]);
        }

        if ($tag !== '') {
            $query->whereHas('icsData', function ($ics) use ($tag) {
                $ics->whereRaw('hashtags::text ilike ?', ['%"' . $tag . '"%']);
            });
        }

        // Hidden-from-directory opt-out; missing key defaults to visible.
        $query->where(function ($w) {
            $w->whereRaw("(settings->>'hide_from_directory') IS DISTINCT FROM 'true'");
        });

        if ($online) {
            $query->whereRaw("(settings->>'is_online') = 'true'");
        }

        if ($priceFilter === 'free') {
            $query->whereDoesntHave('eventTicketTiers', function ($t) {
                $t->where('is_active', true)->where('price_cents', '>', 0);
            });
        } elseif ($priceFilter === 'paid') {
            $query->whereHas('eventTicketTiers', function ($t) {
                $t->where('is_active', true)->where('price_cents', '>', 0);
            });
        }

        [$dateRangeStart, $dateRangeEnd] = static::resolveDateRange($dateFilter, $dateFrom, $dateTo);
        if ($dateRangeStart || $dateRangeEnd) {
            $query->whereHas('icsData', function ($w) use ($dateRangeStart, $dateRangeEnd) {
                if ($dateRangeStart) $w->where('start_date', '>=', $dateRangeStart);
                if ($dateRangeEnd) $w->where('start_date', '<=', $dateRangeEnd);
            });
        }

        // Near-me distance filtering only makes sense for physical events —
        // online events have no coordinates and are always kept in results
        // (they shouldn't be hidden just because the visitor searched near a
        // location), they're simply excluded from the Haversine distance math.
        $nearMe = false;
        if (!$online && $lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            $nearMe = true;
            $lat = (float) $lat;
            $lng = (float) $lng;
            $query->where(function ($w) use ($lat, $lng, $radiusKm) {
                $w->whereRaw("(settings->>'is_online') = 'true'")
                  ->orWhereHas('icsData', function ($ics) use ($lat, $lng, $radiusKm) {
                      $ics->whereNotNull('latitude')->whereNotNull('longitude')
                          ->whereRaw(
                              '(6371 * acos(least(1, greatest(-1,
                                  cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                                  + sin(radians(?)) * sin(radians(latitude))
                              )))) <= ?',
                              [$lat, $lng, $lat, $radiusKm],
                          );
                  });
            });
        }

        $events = $query->orderBy(
            \App\Modules\User\Models\IcsData::select('start_date')
                ->whereColumn('ics_data.link_id', 'links.id')->limit(1)
        )->paginate(24)->withQueryString();

        $storedCategories = Link::where('type', 'ics')
            ->where('is_active', true)
            ->whereRaw("settings->>'event_category' IS NOT NULL")
            ->selectRaw("DISTINCT settings->>'event_category' as category")
            ->pluck('category')->filter();

        // Live event counts per curated slug, used purely to order the browse
        // row (most active categories first) — every enabled admin category
        // is still shown even at a count of 0 (Task #3654).
        $categoryCounts = Link::where('type', 'ics')
            ->where('is_active', true)
            ->whereRaw("settings->>'event_category' IS NOT NULL")
            ->selectRaw("settings->>'event_category' as category, count(*) as cnt")
            ->groupBy('category')
            ->pluck('cnt', 'category');

        $categories = collect($curatedSlugs)
            ->sortByDesc(fn ($slug) => (int) ($categoryCounts[$slug] ?? 0))
            ->values();
        $hasOtherCategory = $storedCategories->contains(
            fn ($c) => !\App\Modules\User\Support\EventCategories::isKnown((string) $c)
        );
        $otherCategory = \App\Modules\User\Support\EventCategories::OTHER;

        // Predefined admin hashtags (Task #3654) are shown first, in their
        // configured order; auto-computed trending tags backfill the rest of
        // the row, deduped against the predefined list.
        $predefinedTags = \App\Modules\Admin\Models\EventHashtag::ordered()->pluck('tag');

        // Trending hashtags: weighted by both recency (sooner events count
        // more than far-off ones) and interest (events more people have
        // marked "interested" count more), instead of a flat occurrence count.
        $trendingTags = \App\Modules\User\Models\IcsData::query()
            ->whereNotNull('hashtags')
            ->whereRaw("jsonb_array_length(hashtags::jsonb) > 0")
            ->whereHas('link', fn ($l) => $l->where('type', 'ics')->where('is_active', true)->where('visibility', 'public'))
            ->where('start_date', '>=', now()->subDay())
            ->with(['link' => function ($l) {
                $l->withCount(['eventInterests as interested_count' => fn ($w) => $w->where('status', 'interested')]);
            }])
            ->get(['id', 'link_id', 'hashtags', 'start_date'])
            ->flatMap(function ($ics) {
                $daysOut = max(1, now()->diffInDays($ics->start_date, false) + 1);
                $recencyWeight  = 1 / $daysOut; // sooner events weigh more heavily
                $interestWeight = 1 + (($ics->link->interested_count ?? 0) * 0.1); // more interest weighs more heavily
                $weight = $recencyWeight * $interestWeight;
                return collect((array) $ics->hashtags)
                    ->map(fn ($t) => mb_strtolower(trim((string) $t)))
                    ->filter()
                    ->map(fn ($t) => ['tag' => $t, 'weight' => $weight]);
            })
            ->groupBy('tag')
            ->map(fn ($rows) => $rows->sum('weight'))
            ->sortDesc()
            ->keys()
            ->values();

        $tagRow = $predefinedTags
            ->merge($trendingTags)
            ->unique()
            ->take(12)
            ->values();

        // Hero slider: a handful of upcoming public events, preferring ones
        // with a cover image so the slider never shows a blank frame. When
        // the visitor has shared/searched a location, feature what's nearby
        // first; otherwise feature what's trending (recent interest,
        // soonest start) instead of a plain chronological list.
        $heroQuery = Link::where('type', 'ics')
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->where(fn ($w) => $w->whereRaw("(settings->>'hide_from_directory') IS DISTINCT FROM 'true'"))
            ->with(['icsData', 'eventTicketTiers' => fn ($t) => $t->where('is_active', true)])
            ->withCount(['eventInterests as interested_count' => function ($w) {
                $w->where('status', 'interested');
            }])
            ->whereHas('icsData', fn ($w) => $w->where('start_date', '>=', now()))
            ->orderByRaw("(select cover_image_url from ics_data where ics_data.link_id = links.id) IS NULL");

        if ($nearMe) {
            // Online events have no coordinates — keep them eligible but push
            // them after events with a resolvable distance, closest first.
            $heroQuery->orderByRaw(
                "(select case
                    when (links.settings->>'is_online') = 'true' then 999999
                    when ics_data.latitude is null or ics_data.longitude is null then 999999
                    else 6371 * acos(least(1, greatest(-1,
                        cos(radians(?)) * cos(radians(ics_data.latitude)) * cos(radians(ics_data.longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(ics_data.latitude))
                    )))
                 end
                 from ics_data where ics_data.link_id = links.id)",
                [$lat, $lng, $lat],
            );
        } else {
            $heroQuery->orderByDesc('interested_count');
        }

        $heroEvents = $heroQuery
            ->orderBy(
                \App\Modules\User\Models\IcsData::select('start_date')
                    ->whereColumn('ics_data.link_id', 'links.id')->limit(1)
            )
            ->limit(3)
            ->get();

        $categoryIcons  = $categories->mapWithKeys(fn ($c) => [$c => static::categoryIcon($c)]);
        $categoryLabels = $categories->mapWithKeys(fn ($c) => [$c => \App\Modules\User\Support\EventCategories::label($c)]);
        $categoryColors = $categories->mapWithKeys(fn ($c) => [$c => \App\Modules\User\Support\EventCategories::gradient($c)]);

        // Reverse nudge (Task #3686): a signed-in creator who owns event
        // links that aren't currently discoverable sees a banner pointing
        // them at those events, using the exact same eligibility rules as
        // the query above so the nudge never contradicts what's listed.
        $ownerHiddenEvents = collect();
        if (auth('web')->check()) {
            $ownerHiddenEvents = \App\Modules\User\Support\EventDirectoryEligibility::nonDiscoverableForOwner(auth('web')->id());
        }

        return view('common.events-directory', compact(
            'events', 'q', 'category', 'tag', 'categories', 'categoryIcons', 'categoryLabels', 'categoryColors',
            'hasOtherCategory', 'otherCategory', 'tagRow', 'nearMe', 'lat', 'lng', 'radiusKm', 'online', 'heroEvents',
            'priceFilter', 'dateFilter', 'dateFrom', 'dateTo', 'ownerHiddenEvents'
        ));
    }

    /**
     * Font Awesome icon for a stored event category (Task #3615). Categories
     * are now picked from a curated list (see EventCategories), so this is an
     * exact slug→icon lookup; legacy free-text values kept from before the
     * curated list fall back to keyword guessing with a calendar-star default.
     */
    protected static function categoryIcon(string $category): string
    {
        return \App\Modules\User\Support\EventCategories::icon($category);
    }

    /**
     * Resolve a [start, end] Carbon range for the date filter. An explicit
     * date_from/date_to pair (custom range) always wins over a quick-range
     * key; either side of a custom range may be omitted (open-ended).
     * Invalid/unparseable dates are treated as "no filter" rather than
     * erroring the request.
     *
     * @return array{0: ?\Illuminate\Support\Carbon, 1: ?\Illuminate\Support\Carbon}
     */
    protected static function resolveDateRange(string $quick, string $dateFrom, string $dateTo): array
    {
        if ($dateFrom !== '' || $dateTo !== '') {
            try {
                $start = $dateFrom !== '' ? \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay() : null;
                $end   = $dateTo !== '' ? \Illuminate\Support\Carbon::parse($dateTo)->endOfDay() : null;
                return [$start, $end];
            } catch (\Throwable $e) {
                return [null, null];
            }
        }

        $now = now();
        switch ($quick) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'weekend':
                $dow = (int) $now->dayOfWeekIso; // 1=Mon .. 7=Sun
                // Days until the *upcoming* Saturday. If today is Sat (6) or
                // Sun (7), treat the current/just-started weekend as "this
                // weekend" rather than jumping a full week ahead.
                $daysUntilSat = $dow >= 6 ? 0 : (6 - $dow);
                $sat = $now->copy()->startOfDay()->addDays($daysUntilSat);
                if ($dow === 7) {
                    // On Sunday, the weekend's Saturday already passed.
                    $sat = $now->copy()->startOfDay()->subDay();
                }
                $sun = $sat->copy()->addDay()->endOfDay();
                return [$sat, $sun];
            case 'week':
                return [$now->copy()->startOfDay(), $now->copy()->endOfWeek()];
            case 'month':
                return [$now->copy()->startOfDay(), $now->copy()->endOfMonth()];
            default:
                return [null, null];
        }
    }
}
