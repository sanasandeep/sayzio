<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\User;
use App\Modules\User\Services\SocialFollowers\FollowerFetcherRegistry;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
    /**
     * Redirect URIs the WebBrowser-based OAuth flow is allowed to bounce
     * back to once the provider returns. The mobile app registers the
     * `1inme://oauth-callback` deep link in its app.json and expects the
     * backend to honor it in every environment.
     *
     * Anything outside this allowlist is dropped and the controller falls
     * back to the safe web-app redirect, so a stolen state cookie cannot
     * be used to redirect a victim's browser to an attacker URL.
     */
    public const MOBILE_RETURN_ALLOWLIST = [
        '1inme://oauth-callback',
    ];

    /**
     * Validate a mobile `?return=` query against the allowlist. Returns
     * the canonical URL on success or null when the value is missing,
     * malformed, or not whitelisted.
     */
    public static function allowedMobileReturn(?string $candidate): ?string
    {
        if ($candidate === null || $candidate === '') {
            return null;
        }
        $candidate = trim($candidate);
        return in_array($candidate, self::MOBILE_RETURN_ALLOWLIST, true) ? $candidate : null;
    }

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

        // Mobile-source markers stashed in loginConnect(). Pull them out
        // up-front so even early failure paths can bounce back to the
        // deep link with an `?error=` rather than dumping the user on the
        // web login page (which is a dead end inside an in-app browser).
        $mobileSource = (string) (session()->pull('social_oauth_source_' . $provider) ?: '');
        $mobileReturn = self::allowedMobileReturn(
            (string) (session()->pull('social_oauth_return_' . $provider) ?: '')
        );
        $isMobile = $mobileSource === 'mobile' && $mobileReturn !== null && $mode === 'login';

        // Pick the right place to bounce back to based on the original
        // intent — login-mode failures should land on the login page,
        // merge-mode failures on the merge page, and connect-mode on
        // the connected-accounts page (which is auth-only).
        $errorRoute = match ($mode) {
            'login' => 'user.login',
            'merge' => 'user.merge.start',
            default => 'user.social-accounts.index',
        };

        $bounceError = function (string $code, string $message) use ($isMobile, $mobileReturn, $errorRoute) {
            if ($isMobile) {
                return redirect()->away($mobileReturn . '?error=' . rawurlencode($code));
            }
            return redirect()->route($errorRoute)->with('error', $message);
        };

        if (! $expected || ! hash_equals($expected, $state)) {
            return $bounceError('invalid_state', 'OAuth state mismatch. Please try again.');
        }
        if ($request->has('error') || $code === '') {
            $providerErr = (string) $request->query('error', 'no code');
            return $bounceError($providerErr, 'Authorization was cancelled or failed: ' . $providerErr);
        }

        // LOGIN-mode: resolve the social identity to its linked account
        // and sign in. Used when an unauthenticated visitor wants to log
        // in with a social provider already attached to an existing
        // account.
        if ($mode === 'login') {
            try {
                [$externalId, $handle, $email] = $oauth->fetchProfile($provider, $code, $state);
            } catch (\Throwable $e) {
                if ($isMobile) {
                    return redirect()->away($mobileReturn . '?error=' . rawurlencode('provider_failed'));
                }
                return redirect()->route('user.login')
                    ->with('error', 'Sign-in with ' . SocialAccountConnection::platformLabel($provider) . ' failed: ' . $e->getMessage());
            }
            $user = LinkedIdentifier::resolveUser('social', '', $provider, (string) $externalId);

            // Email-based resolution / account creation. Only providers that
            // return a verified email (currently Google — `openid email
            // profile`) reach this; every other provider yields a null
            // email and falls straight through to the "no linked account"
            // error below, preserving their existing behaviour. This mirrors
            // the mobile native flow (Api\SocialAuthController::exchange):
            // an unbound social identity whose email matches an existing
            // account is auto-linked to it, otherwise a fresh free-plan
            // account is created. Either way the social identity is then
            // bound so subsequent sign-ins resolve straight to the account.
            $justCreated = false;
            if (! $user && $email) {
                $user = LinkedIdentifier::resolveUser('email', $email)
                    ?: User::where('email', $email)->first();
                if (! $user && \App\Modules\Common\Support\AuthMethods::registrationPaused()) {
                    // No existing account for this social identity/email and an
                    // admin has paused new registrations: create nothing. Web
                    // visitors land on the branded upgrade page (shown at
                    // /register while paused); mobile bounces with a code.
                    if ($isMobile) {
                        return redirect()->away($mobileReturn . '?error=' . rawurlencode(\App\Modules\Common\Support\AuthMethods::ERROR_REGISTRATION_PAUSED));
                    }
                    return redirect()->route('user.register');
                }
                if (! $user) {
                    $freePlan = Plan::defaultPlan();
                    $user = User::create([
                        'name'              => $handle ?: (ucfirst($provider) . ' user'),
                        'email'             => $email,
                        'password'          => Hash::make(Str::random(48)),
                        'plan_id'           => $freePlan?->id,
                        'status'            => 'active',
                        'email_verified_at' => now(),
                    ]);
                    if (method_exists($user, 'ensureDefaultWorkspace')) {
                        $user->ensureDefaultWorkspace();
                    }
                    $justCreated = true;
                }

                // Bind the social identity to the resolved/created account.
                // Stay defensive: if it is somehow already owned by a
                // different account (handle/id legacy drift), don't rebind —
                // fall through and let the visitor sign in via that account
                // through the normal resolve path next time.
                $value    = LinkedIdentifier::normalize('social', '', $provider, (string) $externalId);
                $existsId = LinkedIdentifier::where('kind', 'social')->where('value', $value)->first();
                if (! $existsId) {
                    LinkedIdentifier::create([
                        'user_id'     => $user->id,
                        'kind'        => 'social',
                        'value'       => $value,
                        'provider'    => $provider,
                        'external_id' => (string) $externalId,
                        'verified_at' => now(),
                        'is_primary'  => false,
                    ]);
                } elseif ($existsId->user_id === $user->id) {
                    $existsId->forceFill(['verified_at' => now()])->save();
                }
            }

            if (! $user) {
                if ($isMobile) {
                    return redirect()->away($mobileReturn . '?error=' . rawurlencode('no_linked_account'));
                }
                return redirect()->route('user.login')
                    ->with('error', 'No account is linked to that ' . SocialAccountConnection::platformLabel($provider) . ' identity yet. Sign in another way and link it from Account Settings.');
            }

            // Mobile-source: bounce to the registered deep link with a
            // freshly-minted Sanctum token + minimal user payload, which
            // the app's oauth-callback.tsx already knows how to consume.
            // We deliberately do NOT call Auth::login() — there is no web
            // session for the in-app browser to keep around, and the
            // mobile app authenticates with the bearer token going forward.
            if ($isMobile) {
                $newToken = $user->createToken('mobile-social-' . $provider);
                \App\Jobs\RecordLoginEventJob::dispatch(
                    $user->id,
                    'mobile_social_oauth_' . $provider,
                    (string) ($request->ip() ?? ''),
                    (string) ($request->userAgent() ?? ''),
                    ['personal_access_token_id' => $newToken->accessToken->id ?? null],
                    true,
                    now(),
                );
                $token = $newToken->plainTextToken;
                $payload = json_encode([
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'avatar'     => $user->avatar ?? null,
                    'needs_name' => $justCreated,
                ], JSON_UNESCAPED_SLASHES);
                $qs = http_build_query([
                    'token'    => $token,
                    'user'     => $payload,
                    'provider' => $provider,
                ]);
                return redirect()->away($mobileReturn . '?' . $qs);
            }

            if ($justCreated) {
                session(['auth_needs_name' => true]);
            }
            Auth::login($user, true);
            $request->session()->regenerate();

            \App\Jobs\RecordLoginEventJob::dispatch(
                $user->id,
                'web_social_' . $provider,
                (string) ($request->ip() ?? ''),
                (string) ($request->userAgent() ?? ''),
                ['session_id' => $request->session()->getId()],
                true,
                now(),
            );

            if ($redirect = \App\Modules\Admin\Services\HandleRenameEnforcer::maybeRedirect($user)) {
                return $redirect;
            }
            if (session('auth_needs_name')) {
                return redirect()->route('user.complete.profile');
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
        $conflictUserId = null;
        try {
            $conn = \DB::transaction(function () use ($oauth, $provider, $code, $state, $registry, &$conflictUserId) {
                $conn = $oauth->exchangeAndPersist($provider, Auth::id(), $code, $state);

                // Only create / check a LinkedIdentifier when the provider
                // returned a stable, permanent external ID. Mutable handles
                // must never be used as auth-resolution keys because providers
                // can reassign them, enabling account takeover.
                if (! $conn->external_id) {
                    // No stable ID available — the SocialAccountConnection was
                    // still persisted (for follower-count refreshes) but this
                    // connection cannot be used for social sign-in.
                    return $conn;
                }

                $value = LinkedIdentifier::normalize('social', '', $provider, (string) $conn->external_id);
                $existing = LinkedIdentifier::where('kind', 'social')->where('value', $value)->first();
                if ($existing && $existing->user_id !== Auth::id()) {
                    // Roll the transaction back — this provider identity
                    // is already bound to a different live account; the
                    // user must merge instead of silently rebinding. Stash
                    // the conflicting account id so the catch can offer an
                    // inline merge.
                    $conflictUserId = $existing->user_id;
                    throw new \RuntimeException('__identity_owned_by_other__');
                }
                if (! $existing) {
                    LinkedIdentifier::create([
                        'user_id'     => Auth::id(),
                        'kind'        => 'social',
                        'value'       => $value,
                        'provider'    => $provider,
                        'external_id' => (string) $conn->external_id,
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
                // Offer an inline merge instead of a dead-end error. The
                // OAuth round-trip just proved the signed-in user controls
                // this provider identity, and that identity belongs to
                // $conflictUserId — the same proof the dedicated merge OAuth
                // challenge collects — so accepting can jump straight to the
                // merge preview. The offer is stashed in the session and
                // rendered as a banner on the Connected Accounts page.
                $other = $conflictUserId ? User::find($conflictUserId) : null;
                if ($other && $other->id !== Auth::id()) {
                    session(['social_merge_offer' => [
                        'secondary_id' => $other->id,
                        'provider'     => $provider,
                        'label'        => $other->email ?: ('account #' . $other->id),
                    ]]);
                    return redirect()->route('user.social-accounts.index');
                }
                return redirect()->route('user.social-accounts.index')
                    ->with('error', 'That ' . SocialAccountConnection::platformLabel($provider)
                        . ' identity is already linked to another Sayzio account. Use Account Settings → "Merge another account into this one" to combine them.');
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
        $session = [
            'social_oauth_state_' . $provider => $state,
            'social_oauth_mode_'  . $provider => 'login',
        ];

        // Mobile clients call this endpoint with `?source=mobile&return=...`
        // (see artifacts/1inme-mobile/app/(auth)/index.tsx). The provider
        // doesn't preserve those when it bounces back, so we stash them
        // in the session keyed on provider — alongside the OAuth state —
        // and pull them out in callback() to bounce to the deep link.
        if ((string) $request->query('source') === 'mobile') {
            $return = self::allowedMobileReturn((string) $request->query('return', ''));
            if ($return !== null) {
                $session['social_oauth_source_' . $provider] = 'mobile';
                $session['social_oauth_return_' . $provider] = $return;
            }
        }

        session($session);
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

    /**
     * Accept the inline "merge accounts?" offer raised when a Connect flow
     * found the provider identity already bound to a different account.
     *
     * The connect-mode OAuth round-trip already proved the signed-in user
     * controls that identity (which belongs to the secondary account), so
     * that is sufficient proof to seed the merge challenge and jump straight
     * to the preview — exactly what the dedicated merge OAuth flow does.
     */
    public function acceptMergeOffer(Request $request)
    {
        $offer = session('social_merge_offer');
        session()->forget('social_merge_offer');

        if (! is_array($offer) || empty($offer['secondary_id'])) {
            return redirect()->route('user.social-accounts.index')
                ->with('error', 'That merge offer has expired. Connect the account again to retry.');
        }

        $other = User::find((int) $offer['secondary_id']);
        if (! $other || $other->id === Auth::id()) {
            return redirect()->route('user.social-accounts.index')
                ->with('error', 'The other account could no longer be found.');
        }

        session([
            'merge_secondary_id'     => $other->id,
            'merge_primary_id'       => Auth::id(),
            'merge_challenge_active' => true,
        ]);
        return redirect()->route('user.merge.preview');
    }

    /** Dismiss the inline merge offer and leave the accounts separate. */
    public function declineMergeOffer(Request $request)
    {
        session()->forget('social_merge_offer');
        return redirect()->route('user.social-accounts.index')
            ->with('status', 'No problem — the accounts were left separate.');
    }
}
