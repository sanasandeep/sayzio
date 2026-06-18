<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
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
    public function toAdmin()
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

        return redirect()->route('admin.dashboard')
            ->with('success', 'Switched to the admin dashboard.');
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
