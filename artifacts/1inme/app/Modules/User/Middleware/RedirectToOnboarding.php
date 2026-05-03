<?php

namespace App\Modules\User\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Soft gate that redirects authenticated users to the onboarding wizard
 * the first time they hit the dashboard after verifying their account.
 *
 * Intentionally narrow: only attached to the dashboard route, never to
 * destructive POSTs or API endpoints, so it can't break any other flow
 * if the user happens to be mid-action when their `onboarded_at` is null.
 */
class RedirectToOnboarding
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->onboarded_at === null && $request->isMethod('GET')) {
            return redirect()->route('user.onboarding.index');
        }

        return $next($request);
    }
}
