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
            abort(404, 'Events are not available.');
        }

        return $next($request);
    }
}
