<?php

namespace App\Modules\User\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Soft gate that redirects authenticated users to the onboarding wizard
 * the first time they hit the dashboard after verifying their account.
 *
 * Intentionally narrow: only attached to the dashboard route, never to
 * destructive POSTs or API endpoints, so it can't break any other flow
 * if the user happens to be mid-action when their `onboarded_at` is null.
 */
class RedirectToOnboarding
{
    /**
     * Show the one-time post-registration WhatsApp connect step to a freshly
     * onboarded user who hasn't shared a verified number yet. Recency-gated to
     * recent sign-ups so existing accounts are never force-redirected — they
     * get the gentler dashboard nudge + weekly reminder instead — and the step
     * stamps `whatsapp_step_shown_at` so it only ever fires once.
     */
    private const WHATSAPP_STEP_RECENCY_DAYS = 14;

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $request->isMethod('GET')) {
            if ($user->onboarded_at === null) {
                return redirect()->route('user.onboarding.index');
            }

            $settings = $user->settings ?? [];
            $stepShown = !empty($settings['whatsapp_step_shown_at'] ?? null);
            $recentlyOnboarded = $user->onboarded_at
                && $user->onboarded_at->gt(now()->subDays(self::WHATSAPP_STEP_RECENCY_DAYS));

            if (!$stepShown && $recentlyOnboarded && !$user->hasWhatsappNumber()) {
                return redirect()->route('user.onboarding.whatsapp');
            }
        }

        return $next($request);
    }
}
