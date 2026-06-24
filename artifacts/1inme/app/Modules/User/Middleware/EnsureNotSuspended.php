<?php

namespace App\Modules\User\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-time enforcement of admin temporary holds (Task #2106). The
 * login flow already blocks suspended accounts at sign-in, but an
 * operator can suspend a user who is *already* signed in — this catches
 * that case on the next request and logs them out with the reason.
 *
 * Scoped to the web guard only (admin/API guards are untouched). Holds
 * whose `reactivate_at` has elapsed are auto-lifted so the user isn't
 * locked out past their scheduled date even before the nightly job runs.
 */
class EnsureNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && method_exists($user, 'isSuspended') && $user->isSuspended()) {
            // Auto-lift an elapsed hold instead of locking the user out.
            if ($user->reactivate_at && $user->reactivate_at->isPast()) {
                $user->forceFill([
                    'suspended_at'      => null,
                    'suspension_reason' => null,
                    'suspended_by'      => null,
                    'reactivate_at'     => null,
                ])->save();
                return $next($request);
            }

            // Never bounce an active impersonation session — that's an
            // admin viewing the account, not the suspended user.
            if (session()->has('impersonate_user_id')) {
                return $next($request);
            }

            $reason = trim((string) $user->suspension_reason);
            $message = 'Your account has been suspended.'
                . ($reason !== '' ? ' Reason: ' . $reason : '')
                . ($user->reactivate_at
                    ? ' It is scheduled to be reactivated on ' . $user->reactivate_at->format('M j, Y') . '.'
                    : '');

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => ['message' => $message, 'code' => 'account_suspended']], 403);
            }

            return redirect()->route('user.login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
