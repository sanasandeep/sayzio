<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\GoogleContactsAccount;
use Illuminate\Support\Facades\Log;

/**
 * One-time "your Google Contacts connection expired" alert.
 *
 * Fired only on the FIRST transition into the needs_reauth state (the
 * caller keys this on needs_reauth_at being freshly stamped), so retrying
 * sync jobs can never spam the user. Reconnecting clears needs_reauth_at,
 * arming the notice again for a future expiry.
 *
 * Wholly best-effort: a notification/email failure must never break the
 * sync path that detected the revocation.
 */
class GoogleContactsReauthNotifier
{
    public const TYPE = 'contacts.google_reauth';

    public function send(GoogleContactsAccount $account): void
    {
        $user = $account->user;
        if (!$user) {
            return;
        }

        $accountEmail = $account->account_email ?: 'your Google account';
        $reconnectUrl = AppModulesCommonSupportPlatformHosts::outboundUrl(route('user.contacts.index'));
        $subject      = 'Google Contacts sync is paused — please reconnect';
        $body         = "Google has revoked or expired the connection to {$accountEmail}, so contact syncing is paused."
            . " Reconnect your Google account to resume syncing: {$reconnectUrl}";

        $notifications = app(NotificationService::class);

        $notification = null;
        try {
            $notification = $notifications->notify($user, self::TYPE, [
                'account_id'    => $account->id,
                'account_email' => $account->account_email,
                'subject'       => $subject,
                'body'          => $body,
                'message'       => $body,
                'url'           => $reconnectUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('contacts.google_reauth in-app notify failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }

        if ($user->email && $notifications->prefersChannel($user->id, self::TYPE, 'email')) {
            try {
                Emailer::send(self::TYPE, $user->email, [
                    'account_email' => $accountEmail,
                    'reconnect_url' => $reconnectUrl,
                ], ['user' => $user->id, 'related' => $account]);
            } catch (\Throwable $e) {
                Log::warning('contacts.google_reauth email failed: ' . $e->getMessage(), ['user_id' => $user->id]);
            }
        }

        $notifications->pushToUser(
            $user,
            self::TYPE,
            $subject,
            $body,
            array_merge(
                ['account_id' => $account->id, 'url' => $reconnectUrl],
                $notification ? ['notification_id' => $notification->id] : [],
            ),
        );
    }
}
