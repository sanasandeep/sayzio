<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\GoogleContactsAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-time follow-up for Google Contacts connections that have stayed
 * disconnected (Task #5656). The initial alert fires the moment Google
 * revokes the connection (GoogleContactsReauthNotifier); if the user misses
 * it, syncing stays silently paused. This command finds accounts whose
 * needs_reauth_at is older than 7 days with no reminder stamped yet and
 * sends exactly one reminder (in-app + email + push, honoring per-channel
 * preferences), then stamps reauth_reminder_sent_at so reruns never re-send.
 *
 * Reconnecting clears both needs_reauth_at and reauth_reminder_sent_at
 * (GoogleContactsProvider), re-arming the pair for a future expiry.
 */
class SendGoogleContactsReauthReminders extends Command
{
    public const TYPE = 'contacts.google_reauth_reminder';

    /** Days a connection must stay disconnected before the reminder fires. */
    public const REMINDER_AFTER_DAYS = 7;

    protected $signature = 'contacts:send-reauth-reminders';
    protected $description = 'Send a one-time follow-up reminder for Google Contacts connections still disconnected after 7 days.';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        GoogleContactsAccount::query()
            ->whereNotNull('needs_reauth_at')
            ->whereNull('reauth_reminder_sent_at')
            ->where('needs_reauth_at', '<=', now()->subDays(self::REMINDER_AFTER_DAYS))
            ->orderBy('needs_reauth_at')
            ->chunkById(200, function ($accounts) use ($notifications, &$sent) {
                foreach ($accounts as $account) {
                    $user = $account->user;
                    if (!$user) {
                        // Orphaned row — stamp so we don't re-scan it forever.
                        $account->forceFill(['reauth_reminder_sent_at' => now()])->saveQuietly();
                        continue;
                    }

                    $this->remind($notifications, $account);

                    $account->forceFill(['reauth_reminder_sent_at' => now()])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} Google Contacts reconnect reminders.");
        return self::SUCCESS;
    }

    protected function remind(NotificationService $notifications, GoogleContactsAccount $account): void
    {
        $user         = $account->user;
        $accountEmail = $account->account_email ?: 'your Google account';
        $reconnectUrl = \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('user.contacts.index'));
        $subject      = 'Reminder: Google Contacts sync is still paused';
        $body         = "It's been a week since the connection to {$accountEmail} expired, and contact syncing is still paused."
            . " Reconnect your Google account to resume syncing: {$reconnectUrl}";

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
            Log::warning(self::TYPE . ' in-app notify failed: ' . $e->getMessage(), ['user_id' => $user->id]);
        }

        if ($user->email && $notifications->prefersChannel($user->id, self::TYPE, 'email')) {
            try {
                Emailer::send(self::TYPE, $user->email, [
                    'account_email' => $accountEmail,
                    'reconnect_url' => $reconnectUrl,
                ], ['user' => $user->id, 'related' => $account]);
            } catch (\Throwable $e) {
                Log::warning(self::TYPE . ' email failed: ' . $e->getMessage(), ['user_id' => $user->id]);
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
