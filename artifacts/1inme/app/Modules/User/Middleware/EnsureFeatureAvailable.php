<?php

namespace App\Modules\User\Middleware;

use App\Modules\Common\Support\FeatureStates\FeatureAvailability;
use Closure;
use Illuminate\Http\Request;

/**
 * Guards every authenticated user route that belongs to a catalogue feature.
 * When the feature resolves to "coming soon" (admin-forced or its
 * integration/config isn't connected yet) the request is redirected to the
 * branded preview page instead of hitting a half-wired controller. Ready
 * features pass straight through untouched.
 */
class EnsureFeatureAvailable
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = optional($request->route())->getName();
        $match = FeatureAvailability::forRouteName($routeName);

        // Not a catalogue feature, or the feature is ready → carry on.
        if (!$match || !FeatureAvailability::isComingSoon($match['key'])) {
            return $next($request);
        }

        // Don't redirect the preview page or its notify endpoint onto itself.
        if (in_array($routeName, ['user.coming-soon.show', 'user.coming-soon.notify'], true)) {
            return $next($request);
        }

        return redirect()->route('user.coming-soon.show', ['feature' => $match['key']]);
    }
}
