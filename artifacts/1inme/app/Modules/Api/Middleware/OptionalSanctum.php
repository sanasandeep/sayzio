<?php

namespace App\Modules\Api\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves a bearer token via the sanctum guard if present, but does NOT
 * 401 when no token is provided. Used on public endpoints whose response
 * varies based on the (optional) viewer (e.g. visibility-aware feeds and
 * biolinks).
 */
class OptionalSanctum
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('sanctum')->user();
        if ($user) {
            $request->setUserResolver(fn () => $user);
            // Bind for downstream auth() helpers
            Auth::shouldUse('sanctum');
        }
        return $next($request);
    }
}
