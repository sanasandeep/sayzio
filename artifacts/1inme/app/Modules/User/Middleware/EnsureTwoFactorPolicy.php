<?php

namespace App\Modules\User\Middleware;

use App\Modules\User\Services\TwoFactorPolicy;
use Closure;
use Illuminate\Http\Request;

/**
 * Workspace 2FA gate. Runs after `workspace.scope` so the active workspace
 * is already bound. If the workspace owner requires 2FA and the signed-in
 * member hasn't enrolled (and the grace period has elapsed), bounce them
 * to the forced-setup screen.
 *
 * Always allows the 2FA setup routes themselves and the logout route so
 * the user can actually complete enrollment / sign out without a redirect
 * loop.
 */
class EnsureTwoFactorPolicy
{
    public function __construct(protected TwoFactorPolicy $policy) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        // Always let users finish enrollment / log out / verify the
        // 2FA challenge. Without this allow-list the redirect would loop.
        $allowList = [
            'user.account.two-factor.show',
            'user.account.two-factor.confirm',
            'user.account.two-factor.disable',
            'user.account.two-factor.recovery-codes',
            'user.account.two-factor.required',
            'user.logout',
        ];
        if (in_array($request->route()?->getName(), $allowList, true)) {
            return $next($request);
        }

        if (!app()->bound('current_workspace')) return $next($request);
        $ws = app('current_workspace');
        if (!$ws) return $next($request);

        if ($this->policy->mustEnrollForWorkspace($user, $ws)) {
            return redirect()->route('user.account.two-factor.required');
        }

        return $next($request);
    }
}
