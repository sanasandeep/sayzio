<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\ClientPortal;
use App\Modules\User\Models\ClientPortalAction;
use App\Modules\User\Models\ClientPortalLink;
use Illuminate\Http\Request;

class PortalAuthController extends Controller
{
    /**
     * Magic-link landing. Validates the token, opens the portal session,
     * and redirects into the portal dashboard. The token is consumed off
     * the URL — subsequent requests use the session cookie.
     */
    public function start(Request $request, string $token)
    {
        $link = ClientPortalLink::query()->withoutGlobalScope('workspace')
            ->where('token', $token)->first();

        if (!$link || !$link->isUsable()) {
            return view('portal.gone', ['reason' => $link ? ($link->revoked_at ? 'revoked' : 'expired') : 'invalid']);
        }

        $portal = ClientPortal::query()->withoutGlobalScope('workspace')->find($link->portal_id);
        if (!$portal || !$portal->is_enabled) {
            return view('portal.gone', ['reason' => 'disabled']);
        }

        $request->session()->put('portal_link_id', $link->id);
        $request->session()->regenerate();

        $link->forceFill(['last_used_at' => now()])->save();
        ClientPortalAction::record($portal, $link, 'session_started');

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('portal_link_id');
        $request->session()->invalidate();
        return view('portal.gone', ['reason' => 'logged_out']);
    }

    public function gone()
    {
        return view('portal.gone', ['reason' => 'invalid']);
    }
}
