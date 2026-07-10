<?php

namespace App\Modules\User\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Intercepts authenticated user web requests when the session has an
 * `auth_needs_name` flag set (placed immediately after auto-creating a new
 * account via OTP verify or social sign-in). Redirects to the name-entry
 * form so the user cannot navigate around it.
 *
 * The flag is cleared by AuthController::saveCompleteName() once the user
 * submits a valid name. The middleware is skipped for the complete-profile
 * routes themselves and for sign-out, so neither creates a redirect loop.
 */
class RequiresNameMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::guard('web')->check()
            && $request->session()->get('auth_needs_name')
            && $request->is('user/*')
            && !$request->routeIs(
                'user.complete.profile',
                'user.complete.profile.save',
                'user.logout',
            )
        ) {
            return redirect()->route('user.complete.profile');
        }

        return $next($request);
    }
}
