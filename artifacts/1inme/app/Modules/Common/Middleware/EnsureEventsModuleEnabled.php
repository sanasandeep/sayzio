<?php

namespace App\Modules\Common\Middleware;

use App\Modules\Common\Support\EventsModule;
use Closure;
use Illuminate\Http\Request;

/**
 * Route gate for the platform-wide Events module toggle (Task #6726).
 * Registered as the `events.enabled` alias and applied to every events
 * route (web + API). Returns 404 when the module is off — JSON 404 for
 * API/AJAX callers via Laravel's normal content negotiation.
 */
class EnsureEventsModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (!EventsModule::enabled()) {
            // API/JSON callers keep the plain 404; browser visitors get a
            // branded "Events are unavailable" page (still 404 for SEO).
            if ($request->expectsJson() || $request->is('api/*')) {
                abort(404, 'Events are not available.');
            }

            return response()->view('errors.events-unavailable', [], 404);
        }

        return $next($request);
    }
}
