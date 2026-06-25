<?php

namespace App\Modules\User\Services\SocialFollowers;

use App\Modules\User\Models\SocialAccountConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Transactional "your social connection broke" email.
 *
 * Sent at most once per connection per 7 days. Suppressed for users without
 * a verified email address (so we don't transactional-spam unverified
 * signups). Failure to send is swallowed and logged — the in-app nudge
 * issued elsewhere remains the source of truth.
 */
class SocialConnectionBrokenMail
{
    /** Minimum gap between two "broken" emails for the same connection. */
    public const COOLDOWN_DAYS = 7;

    /**
     * Send the email if the connection has just flipped to broken AND we
     * haven't already emailed about it in the cooldown window. Returns true
     * if an email was actually sent.
     */
    public static function dispatchIfDue(SocialAccountConnection $connection, ?string $reason = null): bool
    {
        $connection->loadMissing('user');
        $user = $connection->user;

        if (! $user || ! $user->email)            return false;
        if (! $user->email_verified_at)           return false;

        // Per-channel opt-out specifically for broken-connection emails.
        // The toggle is exposed in the Connected Accounts UI and the
        // one-click unsubscribe link below flips this same flag. NULL is
        // treated as opted-in so legacy accounts predating the toggle
        // still receive these account-health emails.
        //
        // Note: we deliberately do NOT consult `notify_new_follower` here.
        // That toggle controls "someone followed you" emails, which are a
        // different channel from these account-health alerts; conflating
        // them would either over-suppress (a creator who silenced new-
        // follower emails would never hear that their connection broke)
        // or force creators to keep follow notifications on just to keep
        // health alerts on.
        if ($user->social_connection_broken_emails === false) return false;

        if ($connection->last_broken_email_sent_at
            && $connection->last_broken_email_sent_at->greaterThan(now()->subDays(self::COOLDOWN_DAYS))) {
            return false;
        }

        $platformLabel = SocialAccountConnection::platformLabel($connection->platform);
        $handle        = $connection->handle;
        $subject       = "Your {$platformLabel} link on Sayzio stopped working — reconnect in one click";

        // Signed, no-login-required one-click unsubscribe link. Flips the
        // user's social_connection_broken_emails flag to false. No expiry
        // so creators can act on an old email and still opt out.
        $unsubscribeUrl = URL::signedRoute(
            'user.social-accounts.broken-emails.unsubscribe',
            ['user' => $user->id]
        );

        $viewData = [
            'subject'        => $subject,
            'userName'       => $user->name ?: 'there',
            'platformLabel'  => $platformLabel,
            'handle'         => $handle,
            'reason'         => $reason ?: $connection->last_refresh_error,
            'reconnectUrl'   => route('user.social-accounts.index'),
            'unsubscribeUrl' => $unsubscribeUrl,
        ];

        try {
            Mail::send('emails.social-connection-broken', $viewData, function ($m) use ($user, $subject) {
                $m->to($user->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning("social-connection-broken email failed for connection {$connection->id}: " . $e->getMessage());
            return false;
        }

        $connection->forceFill(['last_broken_email_sent_at' => now()])->save();
        return true;
    }
}
