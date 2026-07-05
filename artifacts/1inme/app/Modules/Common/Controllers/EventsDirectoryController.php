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

        if ($category !== '') {
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

        $nearMe = false;
        if ($lat !== null && $lng !== null && is_numeric($lat) && is_numeric($lng)) {
            $nearMe = true;
            $lat = (float) $lat;
            $lng = (float) $lng;
            $query->whereHas('icsData', function ($w) use ($lat, $lng, $radiusKm) {
                $w->whereNotNull('latitude')->whereNotNull('longitude')
                  ->whereRaw(
                      '(6371 * acos(least(1, greatest(-1,
                          cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                          + sin(radians(?)) * sin(radians(latitude))
                      )))) <= ?',
                      [$lat, $lng, $lat, $radiusKm],
                  );
            });
        }

        $events = $query->orderBy(
            \App\Modules\User\Models\IcsData::select('start_date')
                ->whereColumn('ics_data.link_id', 'links.id')->limit(1)
        )->paginate(24)->withQueryString();

        $categories = Link::where('type', 'ics')
            ->where('is_active', true)
            ->whereRaw("settings->>'event_category' IS NOT NULL")
            ->selectRaw("DISTINCT settings->>'event_category' as category")
            ->pluck('category')->filter()->values();

        // Popular hashtags across public, upcoming events — used to render
        // filter chips in the directory (Task #3593).
        $popularTags = \App\Modules\User\Models\IcsData::query()
            ->whereNotNull('hashtags')
            ->whereRaw("jsonb_array_length(hashtags::jsonb) > 0")
            ->whereHas('link', fn ($l) => $l->where('type', 'ics')->where('is_active', true)->where('visibility', 'public'))
            ->where('start_date', '>=', now()->subDay())
            ->pluck('hashtags')
            ->flatMap(fn ($tags) => (array) $tags)
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(20)
            ->keys()
            ->values();

        $categoryIcons = $categories->mapWithKeys(fn ($c) => [$c => static::categoryIcon($c)]);

        return view('common.events-directory', compact('events', 'q', 'category', 'tag', 'categories', 'categoryIcons', 'popularTags', 'nearMe', 'lat', 'lng', 'radiusKm'));
    }

    /**
     * Best-effort Font Awesome icon for a free-text event category (Task
     * #3614). `event_category` is a plain string the owner types when
     * creating the event (see IcsLinkController), not a fixed enum, so this
     * matches on common keywords with a sensible calendar-star fallback for
     * anything unrecognized.
     */
    protected static function categoryIcon(string $category): string
    {
        $c = mb_strtolower($category);

        $map = [
            'fa-music' => ['music', 'concert', 'dj', 'band'],
            'fa-microchip' => ['tech', 'technology', 'startup', 'coding', 'developer', 'software'],
            'fa-palette' => ['art', 'design', 'craft', 'painting'],
            'fa-utensils' => ['food', 'drink', 'dining', 'restaurant', 'wine', 'beer', 'culinary'],
            'fa-basketball' => ['sport', 'fitness', 'run', 'yoga', 'gym'],
            'fa-briefcase' => ['business', 'networking', 'conference', 'summit', 'career'],
            'fa-heart-pulse' => ['health', 'wellness', 'meditation'],
            'fa-graduation-cap' => ['education', 'workshop', 'class', 'seminar', 'training'],
            'fa-people-group' => ['community', 'social', 'meetup', 'club'],
            'fa-face-laugh' => ['comedy', 'standup'],
            'fa-film' => ['film', 'movie', 'cinema', 'screening'],
            'fa-gamepad' => ['gaming', 'esports'],
            'fa-hand-holding-heart' => ['charity', 'fundraiser', 'nonprofit'],
            'fa-mountain' => ['outdoor', 'travel', 'hiking', 'adventure'],
            'fa-shirt' => ['fashion', 'style'],
            'fa-champagne-glasses' => ['party', 'nightlife', 'festival', 'celebration'],
        ];

        foreach ($map as $icon => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($c, $kw)) {
                    return $icon;
                }
            }
        }

        return 'fa-calendar-star';
    }
}
