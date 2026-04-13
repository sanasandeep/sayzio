<?php

namespace App\Modules\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;

class Impersonate
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('impersonate_user_id') && session()->has('admin_id')) {
            $request->merge(['is_impersonating' => true]);
        }

        return $next($request);
    }
}
