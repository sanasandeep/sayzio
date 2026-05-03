<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalLink;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the current client portal session from session('portal_link_id').
 * Loads the matching link + portal, validates the link is still usable,
 * and binds them in the container as `current_portal_link` / `current_portal`.
 *
 * The signed-in workspace user is intentionally not affected — portal
 * visitors are anonymous and never share an auth boundary with workspace
 * members.
 */
class ResolvePortalSession
{
    public function handle(Request $request, Closure $next)
    {
        $linkId = (int) $request->session()->get('portal_link_id');
        if (!$linkId) {
            return redirect()->route('portal.gone');
        }

        $link = ClientPortalLink::query()->withoutGlobalScope('workspace')->find($linkId);
        if (!$link || !$link->isUsable()) {
            $request->session()->forget('portal_link_id');
            return redirect()->route('portal.gone');
        }

        $portal = ClientPortal::query()->withoutGlobalScope('workspace')
            ->with('workspace')
            ->find($link->portal_id);
        if (!$portal || !$portal->is_enabled) {
            $request->session()->forget('portal_link_id');
            return redirect()->route('portal.gone');
        }

        // Touch usage timestamps (cheap — once per request).
        $link->forceFill(['last_used_at' => now()])->save();
        $portal->forceFill(['last_seen_at' => now()])->save();

        app()->instance('current_portal', $portal);
        app()->instance('current_portal_link', $link);
        $request->attributes->set('current_portal', $portal);
        $request->attributes->set('current_portal_link', $link);

        return $next($request);
    }
}
