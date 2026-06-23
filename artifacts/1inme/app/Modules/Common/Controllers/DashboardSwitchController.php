<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Seamless admin <-> user dashboard switching.
 *
 * Bridges the two auth guards (`web` for the user dashboard, `admin` for
 * the back-office) for a single person whose user and admin records share
 * the same email. Both guards live in the same session, so switching just
 * logs the *other* guard in and redirects — no re-login.
 *
 * This deliberately does NOT touch the impersonation session keys
 * (`impersonate_user_id` / `admin_id`). Those drive the "Admin viewing"
 * banner and the web-logout bridge in {@see \App\Modules\User\Controllers\AuthController::logout}.
 * Dashboard switching is a person moving between their own two dashboards,
 * not an admin viewing someone else's account, so the impersonation flow
 * is left completely intact.
 */
class DashboardSwitchController extends Controller
{
    /**
     * From the user dashboard -> back-office admin dashboard.
     * Requires an authenticated web user with a matching active admin record.
     */
    public function toAdmin(Request $request)
    {
        // Never bridge while an admin is impersonating a user — the web
        // session belongs to the impersonated user, not the operator.
        if (session()->has('impersonate_user_id')) {
            return redirect()->route('user.dashboard');
        }

        $user = Auth::guard('web')->user();
        if (! $user instanceof User) {
            return redirect()->route('user.login');
        }

        $admin = $user->adminAccount();
        if (! $admin || $admin->status !== 'active') {
            return redirect()->route('user.dashboard')
                ->with('error', 'No admin access is available for your account.');
        }

        Auth::guard('admin')->login($admin);

        return redirect($this->resolveSwitchTarget($request))
            ->with('success', 'Switched to the admin dashboard.');
    }

    /**
     * Where to land after switching into the back-office. Defaults to the
     * admin dashboard, but a small allow-list lets callers (eg. the
     * "AI is turned off" page) drop an admin straight onto the relevant
     * settings screen. The allow-list prevents this from becoming an
     * open-redirect — only known internal admin routes are honoured.
     */
    private function resolveSwitchTarget(Request $request): string
    {
        $intent = (string) $request->input('intent', '');

        $targets = [
            'ai-engine' => 'admin.ai-engine.edit',
        ];

        if ($intent !== '' && isset($targets[$intent])) {
            return route($targets[$intent]);
        }

        return route('admin.dashboard');
    }

    /**
     * One-click "Enable AI now" from the "AI is turned off" page.
     *
     * Lets an admin who is browsing their own user dashboard flip the AI
     * master switch (ai.enabled) on without the round-trip through the
     * back-office settings screen, then drops them straight back on the
     * AI feature they originally tried to open.
     *
     * Guarding:
     *   - CSRF is enforced by the web middleware group (POST form token).
     *   - The acting web user must have an active, matching admin record
     *     that holds the `settings.manage` permission — mirroring the
     *     admin.ai-engine.* routes — otherwise we 403.
     *   - We never act while an admin is impersonating a user.
     *
     * If no OpenAI key is configured yet we don't enable the engine (it
     * would just fail on first call); instead we bridge the admin into
     * the back-office and land them on the AI engine settings so they can
     * add a key first.
     */
    public function enableAi(Request $request)
    {
        if (session()->has('impersonate_user_id')) {
            return redirect()->route('user.dashboard');
        }

        $user = Auth::guard('web')->user();
        if (! $user instanceof User) {
            return redirect()->route('user.login');
        }

        $admin = $user->adminAccount();
        if (! $admin || $admin->status !== 'active' || ! $admin->hasPermission('settings.manage')) {
            abort(403, 'Unauthorized action.');
        }

        // No key yet: enabling now would only produce failing calls, so
        // route to settings (bridging the admin guard in) to add one first.
        if (AiEngineSettings::openAiKey() === null) {
            Auth::guard('admin')->login($admin);

            return redirect()->route('admin.ai-engine.edit')
                ->with('error', 'Add an OpenAI API key, then switch the AI engine on.');
        }

        AiEngineSettings::setEnabled(true);

        return redirect($this->resolveAiReturnTarget($request))
            ->with('success', 'AI is now enabled.');
    }

    /**
     * Resolve where to send the admin back to after enabling AI. We honour
     * a `return_to` field carrying the URL of the feature they were on, but
     * only when it points back at this app (same host) to avoid turning the
     * action into an open redirect. Anything else falls back to the user
     * dashboard.
     */
    private function resolveAiReturnTarget(Request $request): string
    {
        $candidate = trim((string) $request->input('return_to', ''));
        if ($candidate !== '') {
            $parts = parse_url($candidate);
            if ($parts !== false) {
                $host = $parts['host'] ?? null;
                if ($host === null || strcasecmp($host, $request->getHost()) === 0) {
                    $path  = $parts['path'] ?? '/';
                    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                    if (str_starts_with($path, '/')) {
                        return $path . $query;
                    }
                }
            }
        }

        return route('user.dashboard');
    }

    /**
     * From the back-office admin dashboard -> user dashboard.
     * Requires an authenticated admin with a matching user record.
     */
    public function toUser()
    {
        // Don't collide with an active impersonation session.
        if (session()->has('impersonate_user_id')) {
            return redirect()->route('user.dashboard');
        }

        $admin = Auth::guard('admin')->user();
        if (! $admin instanceof Admin) {
            return redirect()->route('admin.login');
        }

        $user = $admin->userAccount();
        if (! $user) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'No user account is linked to your admin email.');
        }

        // Ensure the web guard is logged in as the matching user. If a
        // different web user is somehow already attached, replace it so the
        // person always lands on their own dashboard.
        $current = Auth::guard('web')->user();
        if (! $current || (int) $current->id !== (int) $user->id) {
            Auth::guard('web')->login($user);
        }

        return redirect()->route('user.dashboard')
            ->with('success', 'Switched to your user dashboard.');
    }
}
