<?php

namespace App\Modules\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $admin = Auth::guard('admin')->user();

        // Support OR-gating: a pipe-separated list (e.g.
        // "users.grant_admin|users.revoke_admin") passes when the operator
        // holds ANY one of the slugs. A single slug keeps the original
        // all-or-nothing behaviour.
        $slugs = array_values(array_filter(array_map('trim', explode('|', $permission)), 'strlen'));

        $allowed = $admin && (count($slugs) > 1
            ? $admin->hasAnyPermission($slugs)
            : $admin->hasPermission($slugs[0] ?? $permission));

        if (! $allowed) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
