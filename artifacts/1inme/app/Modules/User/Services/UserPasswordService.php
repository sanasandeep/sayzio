<?php

namespace App\Modules\User\Services;

use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Single place that applies a user-chosen password.
 *
 * Every path that lets a user pick a password (change with current-password
 * confirm, OTP-verified first password, public forgot-password reset, and
 * the API equivalents) funnels through {@see apply()} so the security
 * side-effects can never drift between surfaces:
 *
 *   - password + password_set_at stamped together;
 *   - remember_token rotated so stolen remember-me cookies die;
 *   - every OTHER session and Sanctum token revoked (the caller passes the
 *     session id / token id it wants to keep alive, if any);
 *   - a security notification email sent via the Emailer pipeline
 *     (best-effort — a mail outage must never block a password change).
 */
class UserPasswordService
{
    /**
     * @param string      $plainPassword      the new user-chosen password
     * @param string|null $keepSessionId      web session id to keep signed in
     * @param int|null    $keepTokenId        Sanctum personal access token id to keep
     * @param string      $context            'changed' | 'reset' — email copy only
     */
    public function apply(User $user, string $plainPassword, ?string $keepSessionId = null, ?int $keepTokenId = null, string $context = 'changed'): void
    {
        $user->forceFill([
            'password'        => Hash::make($plainPassword),
            'remember_token'  => Str::random(60),
            'password_set_at' => now(),
        ])->save();

        // Revoke every other Sanctum token (mobile / API sessions).
        $tokens = $user->tokens();
        if ($keepTokenId !== null) {
            $tokens->where('id', '!=', $keepTokenId);
        }
        $tokens->delete();

        // Revoke every other web session. Only fully effective on the
        // database session driver; on file-backed dev sessions this no-ops
        // gracefully (the table may not even exist).
        try {
            $q = DB::table('sessions')->where('user_id', $user->id);
            if ($keepSessionId !== null) {
                $q->where('id', '!=', $keepSessionId);
            }
            $q->delete();
        } catch (\Throwable $e) {
            // Non-database session driver — nothing to purge.
        }

        $this->sendNotification($user, $context);
    }

    /** Whether this account has a password the user actually chose. */
    public function hasChosenPassword(User $user): bool
    {
        return $user->password_set_at !== null;
    }

    /**
     * Best-effort "your password was changed" security email. Failure is
     * logged by the Emailer pipeline (email_logs) and never surfaced —
     * the password change itself must always succeed.
     */
    private function sendNotification(User $user, string $context): void
    {
        if (blank($user->email)) {
            return; // WhatsApp/mobile-only account — nowhere to send.
        }

        try {
            Emailer::send('security.password_changed', $user->email, [
                'name'   => $user->name ?: 'there',
                'action' => $context === 'reset' ? 'reset' : 'changed',
                'time'   => now()->timezone($user->effectiveTimezone())->format('M j, Y g:i A T'),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Password-changed notification failed: ' . $e->getMessage());
        }
    }
}
