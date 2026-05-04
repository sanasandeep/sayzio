<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Carbon\Carbon;

/**
 * Centralizes "is this user required to have 2FA right now?" so the
 * middleware, the team compliance view and the forced-enrollment screen
 * all answer the question identically.
 *
 * A workspace owner can flip `require_2fa` on at any time and optionally
 * set a grace deadline (`2fa_grace_until`). Until that deadline passes
 * un-enrolled members can still use the app — they only see a banner /
 * email reminder. After the deadline, any workspace request from an
 * un-enrolled member is intercepted and forced through TOTP setup.
 */
class TwoFactorPolicy
{
    /** True if this workspace currently requires its members to have 2FA. */
    public function workspaceRequires2FA(Workspace $workspace): bool
    {
        return (bool) (($workspace->settings ?? [])['require_2fa'] ?? false);
    }

    /**
     * Optional grace deadline; null means "enforce immediately when policy
     * is on". Returns a Carbon instance or null.
     */
    public function workspaceGraceDeadline(Workspace $workspace): ?Carbon
    {
        $raw = ($workspace->settings ?? [])['2fa_grace_until'] ?? null;
        if (!$raw) return null;
        try { return Carbon::parse($raw); } catch (\Throwable $e) { return null; }
    }

    /** True if the grace deadline (if any) has elapsed. */
    public function workspaceGraceExpired(Workspace $workspace): bool
    {
        $deadline = $this->workspaceGraceDeadline($workspace);
        return !$deadline || $deadline->isPast();
    }

    /** True if the user has finished enrolling a TOTP authenticator. */
    public function userHasEnrolledTotp(User $user): bool
    {
        return !empty($user->two_factor_secret) && !empty($user->two_factor_confirmed_at);
    }

    /**
     * Should this signed-in user be redirected to forced enrollment for the
     * given workspace right now? Owners are exempt because they are the
     * only ones who can turn the policy off, but they get a separate banner
     * encouraging them to lead by example.
     */
    public function mustEnrollForWorkspace(User $user, Workspace $workspace): bool
    {
        if (!$this->workspaceRequires2FA($workspace)) return false;
        if ($this->userHasEnrolledTotp($user)) return false;
        if ((int) $workspace->owner_user_id === (int) $user->id) return false;
        return $this->workspaceGraceExpired($workspace);
    }

    /**
     * Across every workspace this user can access, is there at least one
     * that requires 2FA? Used by the post-login redirect even before a
     * specific workspace context is bound.
     */
    public function userIsCoveredByAnyPolicy(User $user): bool
    {
        foreach ($user->accessibleWorkspaces() as $ws) {
            if ($this->workspaceRequires2FA($ws)) return true;
        }
        return false;
    }
}
