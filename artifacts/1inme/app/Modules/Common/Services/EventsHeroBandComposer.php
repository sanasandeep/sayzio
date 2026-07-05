<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use Illuminate\View\View;

/**
 * Shares a handful of featured upcoming events with the reusable events-hero
 * promo band (`common.partials.events-hero-band`), so every marketing page
 * that includes the band gets fresh data without each controller having to
 * fetch it. Mirrors the "trending" branch of
 * EventsDirectoryController::index()'s heroEvents query (no near-me/location
 * bias here, since the band has no search form to attach it to).
 */
class EventsHeroBandComposer
{
    /** View that renders the band; composing it directly means every page
     *  that @includes it gets the data, with no per-page wiring. */
    public const VIEW = 'common.partials.events-hero-band';

    public function compose(View $view): void
    {
        $view->with('heroBandEvents', $this->featuredEvents());
    }

    protected function featuredEvents()
    {
        return Link::where('type', 'ics')
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->where(fn ($w) => $w->whereRaw("(settings->>'hide_from_directory') IS DISTINCT FROM 'true'"))
            ->with(['icsData', 'eventTicketTiers' => fn ($t) => $t->where('is_active', true)])
            ->withCount(['eventInterests as interested_count' => function ($w) {
                $w->where('status', 'interested');
            }])
            ->whereHas('icsData', fn ($w) => $w->where('start_date', '>=', now()))
            ->orderByRaw("(select cover_image_url from ics_data where ics_data.link_id = links.id) IS NULL")
            ->orderByDesc('interested_count')
            ->orderBy(
                IcsData::select('start_date')
                    ->whereColumn('ics_data.link_id', 'links.id')->limit(1)
            )
            ->limit(3)
            ->get();
    }
}
