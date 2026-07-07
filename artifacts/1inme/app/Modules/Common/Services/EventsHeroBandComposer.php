<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use Illuminate\Support\Facades\Cache;
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

    /**
     * The band is included from the shared marketing layout, so its three
     * queries (links + ics_data + ticket tiers) used to run on EVERY public
     * marketing page render. In production (DB_PERSISTENT=false against the
     * cross-region RDS) even one per-request query costs a fresh ~3s SSL
     * connect, so the payload is cached for 5 minutes as PLAIN ATTRIBUTE
     * ARRAYS and rehydrated on read — never serialized models, which don't
     * survive the file cache (__PHP_Incomplete_Class on unserialize).
     */
    public const CACHE_KEY = 'events:hero_band:v1';

    public function compose(View $view): void
    {
        $view->with('heroBandEvents', $this->featuredEvents());
    }

    /**
     * Build the cacheable payload (plain attribute arrays). Shared by the
     * request-path Cache::remember below and MarketingPageCache::warm(), so
     * the warmer and the lazy rebuild can never drift.
     */
    public function buildRows(): array
    {
        return $this->queryFeaturedEvents()
            ->map(fn (Link $l) => [
                'link'  => $l->getAttributes(),
                'ics'   => $l->icsData?->getAttributes(),
                'tiers' => $l->eventTicketTiers->map(fn ($t) => $t->getAttributes())->all(),
            ])
            ->all();
    }

    protected function featuredEvents()
    {
        try {
            $rows = Cache::remember(self::CACHE_KEY, 300, fn () => $this->buildRows());
        } catch (\Throwable $e) {
            // Events tables not migrated yet — the band simply hides.
            return collect();
        }

        return collect($rows)->map(function (array $row) {
            $link = Link::query()->hydrate([$row['link']])->first();
            $link->setRelation('icsData', !empty($row['ics'])
                ? IcsData::query()->hydrate([$row['ics']])->first()
                : null);
            $link->setRelation('eventTicketTiers', EventTicketTier::hydrate($row['tiers'] ?? []));

            return $link;
        });
    }

    protected function queryFeaturedEvents()
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
