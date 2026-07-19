<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\ZioBrowserRelease;
use Illuminate\Support\Facades\Cache;

/**
 * Public /download page for the SayZio Browser desktop app.
 *
 * Installer links come from the last-known GitHub release cached by the
 * scheduled `zio-browser:refresh-release` job (stale-while-revalidate — see
 * ZioBrowserRelease). This controller NEVER performs a live GitHub call, so
 * the page always renders instantly from cache, the durably persisted
 * last-good release, or the pinned fallback.
 */
class ZioBrowserDownloadController extends Controller
{
    public function show()
    {
        $release = ZioBrowserRelease::current();

        // Cache miss (fresh deploy / flushed cache): serve the last-good /
        // fallback now and refresh AFTER the response so the next visitor
        // gets the live release without anyone waiting on GitHub. Cache::add
        // throttles the attempts so a burst of visitors can't stampede the API.
        if (!Cache::has(ZioBrowserRelease::CACHE_KEY)
            && Cache::add(ZioBrowserRelease::REFRESH_LOCK_KEY, 1, ZioBrowserRelease::REFRESH_LOCK_TTL)) {
            dispatch(function (): void {
                ZioBrowserRelease::refresh();
            })->afterResponse();
        }

        return view('public.download', ['release' => $release, 'seoKey' => 'download']);
    }
}
