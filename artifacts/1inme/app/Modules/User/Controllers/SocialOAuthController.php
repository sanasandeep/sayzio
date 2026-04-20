<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\User;
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
        $mode     = (string) (session()->pull('social_oauth_mode_' . $provider) ?: 'connect');

        // Pick the right place to bounce back to based on the original
        // intent — login-mode failures should land on the login page,
        // merge-mode failures on the merge page, and connect-mode on
        // the connected-accounts page (which is auth-only).
        $errorRoute = match ($mode) {
            'login' => 'user.login',
            'merge' => 'user.merge.start',
            default => 'user.social-accounts.index',
        };

        if (! $expected || ! hash_equals($expected, $state)) {
            return redirect()->route($errorRoute)
                ->with('error', 'OAuth state mismatch. Please try again.');
        }
        if ($request->has('error') || $code === '') {
            return redirect()->route($errorRoute)
                ->with('error', 'Authorization was cancelled or failed: ' . $request->query('error', 'no code'));
        }

        // LOGIN-mode: resolve the social identity to its linked account
        // and sign in. Used when an unauthenticated visitor wants to log
        // in with a social provider already attached to an existing
        // account.
        if ($mode === 'login') {
            try {
                [$externalId, $handle] = $oauth->fetchProfile($provider, $code, $state);
            } catch (\Throwable $e) {
                return redirect()->route('user.login')
                    ->with('error', 'Sign-in with ' . SocialAccountConnection::platformLabel($provider) . ' failed: ' . $e->getMessage());
            }
            $user = LinkedIdentifier::resolveUser('social', '', $provider, (string) $externalId);
            // Legacy-row fallback: some pre-fix connections were saved
            // keyed by handle when external_id was unavailable. Try that
            // before giving up.
            if (! $user && $handle) {
                $user = LinkedIdentifier::resolveUser('social', '', $provider, (string) $handle);
            }
            if (! $user) {
                return redirect()->route('user.login')
                    ->with('error', 'No account is linked to that ' . SocialAccountConnection::platformLabel($provider) . ' identity yet. Sign in another way and link it from Account Settings.');
            }
            Auth::login($user, true);
            $user->update(['last_login_at' => now()]);
            $request->session()->regenerate();

            if ($redirect = \App\Modules\Admin\Services\HandleRenameEnforcer::maybeRedirect($user)) {
                return $redirect;
            }
            return redirect()->intended(route('user.dashboard'));
        }

        // MERGE-mode: prove ownership of another account that has the
        // given social provider linked, then continue into the merge
        // preview flow.
        if ($mode === 'merge') {
            // The merge challenge must be completed by the same user who
            // started it. If their session has expired or they signed
            // out before the provider redirected back, refuse outright —
            // we must never set merge_secondary_id under an unbound or
            // attacker-controlled session.
            if (! Auth::check()) {
                $request->session()->forget([
                    'social_oauth_state_' . $provider,
                    'social_oauth_mode_' . $provider,
                    'merge_secondary_id', 'merge_primary_id', 'merge_challenge_active',
                ]);
                return redirect()->route('user.login')
                    ->with('error', 'Your session expired before the merge could complete. Please sign in and start again.');
            }
            try {
                [$externalId, $handle] = $oauth->fetchProfile($provider, $code, $state);
            } catch (\Throwable $e) {
                return redirect()->route('user.merge.start')
                    ->with('error', 'Verification failed: ' . $e->getMessage());
            }
            $other = LinkedIdentifier::resolveUser('social', '', $provider, (string) $externalId);
            if (! $other && $handle) {
                $other = LinkedIdentifier::resolveUser('social', '', $provider, (string) $handle);
            }
            if (! $other || $other->id === Auth::id()) {
                return redirect()->route('user.merge.start')
                    ->with('error', 'No other account is linked to that ' . SocialAccountConnection::platformLabel($provider) . ' identity.');
            }
            session([
                'merge_secondary_id' => $other->id,
                'merge_primary_id'   => Auth::id(),
            ]);
            return redirect()->route('user.merge.preview');
        }

        // Connect-mode requires an authenticated user.
        if (! Auth::check()) {
            return redirect()->route('user.login')
                ->with('error', 'Please sign in before connecting a social account.');
        }

        // Wrap connect+ownership-check in a single transaction so that if
        // the social identity already belongs to another account, we
        // don't leave behind a half-written SocialAccountConnection.
        try {
            $conn = \DB::transaction(function () use ($oauth, $provider, $code, $state, $registry) {
                $conn = $oauth->exchangeAndPersist($provider, Auth::id(), $code, $state);
                $value = LinkedIdentifier::normalize('social', '', $provider, (string) ($conn->external_id ?: $conn->handle));
                $existing = LinkedIdentifier::where('kind', 'social')->where('value', $value)->first();
                if ($existing && $existing->user_id !== Auth::id()) {
                    // Roll the transaction back — this provider identity
                    // is already bound to a different live account; the
                    // user must merge instead of silently rebinding.
                    throw new \RuntimeException('__identity_owned_by_other__');
                }
                if (! $existing) {
                    LinkedIdentifier::create([
                        'user_id'     => Auth::id(),
                        'kind'        => 'social',
                        'value'       => $value,
                        'provider'    => $provider,
                        'external_id' => (string) ($conn->external_id ?: $conn->handle),
                        'verified_at' => now(),
                        'is_primary'  => false,
                    ]);
                } else {
                    $existing->verified_at = now();
                    $existing->save();
                }
                return $conn;
            });
            $registry->refresh($conn);
        } catch (\Throwable $e) {
            if ($e->getMessage() === '__identity_owned_by_other__') {
                return redirect()->route('user.social-accounts.index')
                    ->with('error', 'That ' . SocialAccountConnection::platformLabel($provider)
                        . ' identity is already linked to another 1INME account. Use Account Settings → "Merge another account into this one" to combine them.');
            }
            return redirect()->route('user.social-accounts.index')
                ->with('error', 'Connect failed: ' . $e->getMessage());
        }

        return redirect()->route('user.social-accounts.index')
            ->with('success', SocialAccountConnection::platformLabel($provider) . ' account connected.');
    }

    /**
     * Begin a "Sign in with <provider>" flow for an unauthenticated
     * visitor. Mirrors connect() but stores a login-mode marker.
     */
    public function loginConnect(Request $request, string $provider, SocialOAuthService $oauth)
    {
        abort_unless(isset(SocialOAuthService::PROVIDERS[$provider]), 404);
        if (! $oauth->isConfigured($provider)) {
            return redirect()->route('user.login')
                ->with('error', SocialAccountConnection::platformLabel($provider)
                    . ' sign-in is not configured on this server.');
        }
        $state = Str::random(64);
        session([
            'social_oauth_state_' . $provider => $state,
            'social_oauth_mode_'  . $provider => 'login',
        ]);
        return redirect()->away($oauth->authorizeUrl($provider, $state));
    }

    /** Begin a merge-challenge OAuth flow for the signed-in user. */
    public function mergeConnect(Request $request, string $provider, SocialOAuthService $oauth)
    {
        abort_unless(isset(SocialOAuthService::PROVIDERS[$provider]), 404);
        if (! $oauth->isConfigured($provider)) {
            return redirect()->route('user.merge.start')
                ->with('error', SocialAccountConnection::platformLabel($provider)
                    . ' OAuth is not configured on this server.');
        }
        $state = Str::random(64);
        session([
            'social_oauth_state_' . $provider => $state,
            'social_oauth_mode_'  . $provider => 'merge',
        ]);
        return redirect()->away($oauth->authorizeUrl($provider, $state));
    }
}
