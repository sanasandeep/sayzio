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

        if (!$admin || !$admin->hasPermission($permission)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
