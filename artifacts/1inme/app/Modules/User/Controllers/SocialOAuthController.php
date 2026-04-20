<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Services\SocialFollowers\FollowerFetcherRegistry;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Handles the "Connect with <Platform>" buttons on the Connected Accounts page.
 *
 *   1. /user/social-oauth/{provider}/connect  -> redirect to provider authorize URL
 *   2. /user/social-oauth/{provider}/callback -> exchange code, persist connection
 *
 * Manual token paste in SocialAccountController@store remains supported as a
 * fallback for providers without configured OAuth credentials.
 */
class SocialOAuthController extends Controller
{
    public function connect(Request $request, string $provider, SocialOAuthService $oauth)
    {
        abort_unless(isset(SocialOAuthService::PROVIDERS[$provider]), 404);
        if (! $oauth->isConfigured($provider)) {
            return redirect()->route('user.social-accounts.index')
                ->with('error', SocialAccountConnection::platformLabel($provider)
                    . ' OAuth is not configured on this server. Paste a token manually for now.');
        }

        // Random state used for CSRF protection AND (when PKCE is in play)
        // doubles as the code_verifier. Stored in session, single-use.
        $state = Str::random(64);
        session(['social_oauth_state_' . $provider => $state]);

        return redirect()->away($oauth->authorizeUrl($provider, $state));
    }

    public function callback(Request $request, string $provider, SocialOAuthService $oauth, FollowerFetcherRegistry $registry)
    {
        abort_unless(isset(SocialOAuthService::PROVIDERS[$provider]), 404);

        $expected = session()->pull('social_oauth_state_' . $provider);
        $state    = (string) $request->query('state', '');
        $code     = (string) $request->query('code', '');

        if (! $expected || ! hash_equals($expected, $state)) {
            return redirect()->route('user.social-accounts.index')
                ->with('error', 'OAuth state mismatch. Please try connecting again.');
        }
        if ($request->has('error') || $code === '') {
            return redirect()->route('user.social-accounts.index')
                ->with('error', 'Authorization was cancelled or failed: ' . $request->query('error', 'no code'));
        }

        try {
            $conn = $oauth->exchangeAndPersist($provider, Auth::id(), $code, $state);
            $registry->refresh($conn);
        } catch (\Throwable $e) {
            return redirect()->route('user.social-accounts.index')
                ->with('error', 'Connect failed: ' . $e->getMessage());
        }

        return redirect()->route('user.social-accounts.index')
            ->with('success', SocialAccountConnection::platformLabel($provider) . ' account connected.');
    }
}
