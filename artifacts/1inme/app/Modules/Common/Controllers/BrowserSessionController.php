<?php

namespace App\Modules\Common\Controllers;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Zio Browser web-session bridge (see Api\AuthController::browserSession).
 *
 * GET /browser/session-login is reachable only via a temporarySignedRoute
 * URL minted for an authenticated Sanctum caller. The `signed` middleware
 * verifies the HMAC + expiry; this controller additionally burns the nonce
 * so each URL logs in exactly once, then establishes the web session for
 * the token's own user and lands on the dashboard.
 */
class BrowserSessionController extends Controller
{
    public function login(Request $request)
    {
        $nonce = (string) $request->query('nonce', '');
        if ($nonce === '' || ! Cache::add('browser-session-login:' . $nonce, 1, now()->addMinutes(10))) {
            abort(403, 'This sign-in link has already been used.');
        }

        $user = User::find((int) $request->query('user'));
        if (! $user) {
            abort(403);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/user/dashboard');
    }
}
