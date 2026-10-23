<?php

namespace App\Modules\Api\Middleware;

use App\Modules\Admin\Support\StaffPermissionCatalog;
use Closure;
use Illuminate\Http\Request;

/** Linked staff cannot bypass back-office identity restrictions through bearer APIs. */
class StaffPrivacy
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/v1/admin/*') || $request->is('v1/admin/*')) {
            $user = $request->user();
            $admin = $user instanceof \App\Modules\User\Models\User ? $user->adminAccount() : null;
            if ($admin) {
                abort_unless($admin->status === 'active', 403);
                $projected = $request->route()?->getControllerClass() === \App\Modules\Api\Controllers\AdminAccessController::class
                    && in_array($request->route()?->getActionMethod(), ['context', 'users'], true);
                if (! $projected) {
                    abort_unless(StaffPermissionCatalog::fullIdentity($admin), 403);
                }
            }
        }
        return $next($request);
    }
}
