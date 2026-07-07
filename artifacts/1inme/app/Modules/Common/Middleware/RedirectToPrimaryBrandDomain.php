<?php

namespace App\Modules\Common\Middleware;

use App\Modules\Common\Support\PlatformHosts;
use Closure;
use Illuminate\Http\Request;

/**
 * Consolidates the public marketing surface onto the primary brand domain
 * (sayzio.app) so search engines never see the same landing pages as
 * duplicate content across both brand domains.
 *
 * Only recognised *non-primary* brand domains (currently 1in.me) are
 * redirected — dev/preview hosts (Replit dev domain, localhost) and custom
 * user domains are left alone, since 1in.me is dedicated to short
 * links/profiles and legitimately should not out-rank sayzio.app for these
 * marketing routes. GET/HEAD only; a 301 preserves the full path and query
 * string so bookmarks/backlinks keep working.
 */
class RedirectToPrimaryBrandDomain
{
    public function handle(Request $request, Closure $next)
    {
        if (
            ($request->isMethod('GET') || $request->isMethod('HEAD'))
            && PlatformHosts::isNonPrimaryBrandDomain($request->getHost())
        ) {
            $primary = PlatformHosts::primaryBrandDomain();
            if ($primary !== null) {
                $target = $request->getScheme() . '://' . $primary . $request->getRequestUri();

                return redirect()->away($target, 301);
            }
        }

        return $next($request);
    }
}
