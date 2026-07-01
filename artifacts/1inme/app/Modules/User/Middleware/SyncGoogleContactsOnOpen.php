<?php

namespace App\Modules\User\Middleware;

use App\Jobs\SyncGoogleContactsJob;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fires a throttle-respecting Google Contacts sync when the user opens the
 * web app, so their contacts reflect changes made in Google (or on another
 * device) within seconds instead of waiting for the scheduled backstop.
 *
 * The sync is dispatched afterResponse so it never blocks the page render,
 * and a short session window keeps us from queueing on every single request.
 * The real per-account guard is the cooldown inside
 * {@see \App\Modules\User\Services\Contacts\GoogleContactsSyncService::syncNow()},
 * so this window only trims redundant dispatch overhead.
 */
class SyncGoogleContactsOnOpen
{
    /** Seconds between two on-open dispatches within the same session. */
    private const OPEN_WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only genuine page opens: authenticated GET requests rendering HTML.
        // Skip AJAX/JSON polls and non-GET verbs so we don't dispatch on every
        // background fetch the app makes.
        if (! $request->isMethod('GET')
            || $request->ajax()
            || $request->expectsJson()
            || ! ($user = $request->user())) {
            return $response;
        }

        $now  = time();
        $last = (int) $request->session()->get('gcontacts_open_sync_at', 0);
        if ($last && ($now - $last) < self::OPEN_WINDOW_SECONDS) {
            return $response;
        }

        // Cheap existence check before we bother scheduling anything.
        if (! SyncGoogleContactsJob::shouldQueue($user->id)) {
            return $response;
        }

        $request->session()->put('gcontacts_open_sync_at', $now);
        SyncGoogleContactsJob::dispatch($user->id)->afterResponse();

        return $response;
    }
}
