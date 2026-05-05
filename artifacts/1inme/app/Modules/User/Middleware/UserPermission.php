<?php

namespace App\Modules\User\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * User-pool permission gate. Replaces the legacy `SuperAdmin` middleware.
 *
 * Usage:
 *   Route::middleware('user.can:user.plans.manage')->group(...);
 *
 * Multiple permissions are OR-ed: if the user holds any one of the
 * listed permissions, the request is allowed.
 *
 * Important: there is intentionally NO short-circuit for a "super admin"
 * flag on the user side. Access is decided exclusively by the
 * permissions attached to the user's roles.
 */
class UserPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if (empty($permissions)) {
            abort(500, 'user.can middleware requires at least one permission slug.');
        }

        if (!method_exists($user, 'hasAnyPermission')) {
            abort(403, 'Access denied.');
        }

        if (!$user->hasAnyPermission($permissions)) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'error'       => 'forbidden',
                    'reason'      => 'missing_permission',
                    'permissions' => $permissions,
                ], 403);
            }
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
