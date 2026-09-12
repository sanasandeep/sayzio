<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\SiteStat;
use App\Modules\Admin\Models\Testimonial;
use App\Modules\Admin\Models\ZioLine;
use App\Modules\Common\Services\EventsHeroBandComposer;
use App\Modules\Common\Support\HomePageCache;
use App\Modules\Common\Support\MarketingPageCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * "Rebuild public site cache" -- the manual version of the `home:warm-caches`
 * command the scheduler runs every four minutes.
 *
 * The public pages read almost everything through short-lived caches, so a
 * change made in admin normally appears either instantly (each screen drops
 * its own key on save) or within one warm cadence. This button exists for
 * the times that is not enough: a change made straight in the database, a
 * screen whose flush is still missing, or simply wanting to see the live
 * site update now rather than explain to somebody why it has not.
 *
 * It flushes first and then rebuilds, so the button is never the reason a
 * visitor lands on a cold page: by the time the redirect renders, the keys
 * hold freshly built payloads rather than nothing at all.
 */
class MarketingCacheController extends Controller
{
    public function refresh()
    {
        $started = microtime(true);

        // Keys owned by an admin screen rather than by the warmer.
        SiteStat::flushCache();
        ZioLine::flushCache();
        Testimonial::flushCache();
        Cache::forget(EventsHeroBandComposer::CACHE_KEY);

        try {
            $home      = HomePageCache::warm();
            $marketing = MarketingPageCache::warm();
        } catch (\Throwable $e) {
            Log::error('Manual marketing cache rebuild failed: '.$e->getMessage());

            return back()->with(
                'error',
                'The cache was cleared, but the rebuild did not finish. '
                .'The public pages will rebuild themselves on the next visit.'
            );
        }

        $errors  = array_merge($home['errors'] ?? [], $marketing['errors'] ?? []);
        $seconds = number_format(microtime(true) - $started, 1);

        if ($errors !== []) {
            return back()->with('error', sprintf(
                'Rebuilt the public site cache in %ss, but %d section(s) failed: %s',
                $seconds,
                count($errors),
                implode('; ', array_slice($errors, 0, 3))
            ));
        }

        return back()->with('success', sprintf(
            'Public site cache rebuilt in %ss. The homepage, About and Features '
            .'now show what is in admin.',
            $seconds
        ));
    }
}
