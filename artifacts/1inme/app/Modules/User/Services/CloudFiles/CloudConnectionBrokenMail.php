<?php

namespace App\Modules\User\Services\CloudFiles;

use App\Modules\User\Models\CloudConnection;
use App\Modules\User\Models\CloudProviderApp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Transactional "your cloud connection broke" email.
 *
 * Sent at most once per connection per 7 days. Suppressed for users without
 * a verified email address. Failure to send is swallowed and logged — the
 * in-app sidebar banner remains the source of truth.
 *
 * Mirrors SocialConnectionBrokenMail but for cloud_connections (Google
 * Drive / Dropbox / OneDrive). The two channels are deliberately separate:
 * a creator who silenced one shouldn't lose visibility on the other.
 */
class CloudConnectionBrokenMail
{
    /** Minimum gap between two "broken" emails for the same connection. */
    public const COOLDOWN_DAYS = 7;

    /**
     * Send the email if the connection has just flipped to broken AND we
     * haven't already emailed about it in the cooldown window. Returns true
     * if an email was actually sent.
     */
    public static function dispatchIfDue(CloudConnection $connection, ?string $reason = null): bool
    {
        $connection->loadMissing('user');
        $user = $connection->user;

        if (! $user || ! $user->email)            return false;
        if (! $user->email_verified_at)           return false;

        if ($connection->last_broken_email_sent_at
            && $connection->last_broken_email_sent_at->greaterThan(now()->subDays(self::COOLDOWN_DAYS))) {
            return false;
        }

        $providerLabel = CloudProviderApp::PROVIDER_LABELS[$connection->provider] ?? $connection->provider;
        $accountLabel  = $connection->account_label ?: ($connection->account_email ?: $providerLabel);
        $subject       = "Your {$providerLabel} connection on Sayzio stopped working — reconnect in one click";

        $viewData = [
            'subject'        => $subject,
            'userName'       => $user->name ?: 'there',
            'providerLabel'  => $providerLabel,
            'accountLabel'   => $accountLabel,
            'reason'         => $reason ?: $connection->last_error,
            'reconnectUrl'   => route('user.cloud-files.connections'),
        ];

        try {
            \App\Modules\Common\Services\Emailer::send('connections.cloud_broken', $user->email, [
                'provider' => $providerLabel,
            ], [
                'user'      => $user->id,
                'related'   => $connection,
                'subject'   => $subject,
                'view_data' => $viewData,
            ]);
        } catch (\Throwable $e) {
            Log::warning("cloud-connection-broken email failed for connection {$connection->id}: " . $e->getMessage());
            return false;
        }

        $connection->forceFill(['last_broken_email_sent_at' => now()])->save();
        return true;
    }
}
