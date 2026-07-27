<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;

/**
 * Delivers dialer call-back reminders that have come due. A reminder is a
 * dialer_lookups row with a `callback_at` in the past and no
 * `callback_notified_at` stamp yet. We deliver in-app (+ push, best-effort)
 * exactly once and stamp the row so reruns are idempotent.
 *
 * Runs every five minutes from the scheduler.
 */
class SendDialerCallbackReminders extends Command
{
    protected $signature = 'dialer:send-callback-reminders';
    protected $description = 'Deliver due dialer call-back reminders (in-app + push), once each.';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        DialerLookup::query()
            ->whereNotNull('callback_at')
            ->whereNull('callback_notified_at')
            ->where('callback_at', '<=', now())
            ->with(['contact'])
            ->orderBy('callback_at')
            ->chunkById(200, function ($rows) use ($notifications, &$sent) {
                foreach ($rows as $row) {
                    $user = User::find($row->user_id);
                    if (!$user) {
                        // Orphaned row — stamp so we don't re-scan it forever.
                        $row->callback_notified_at = now();
                        $row->save();
                        continue;
                    }

                    $who = $row->contact?->nameForDisplay() ?: ($row->number_e164 ?: 'a contact');
                    $message = "Time to call back {$who}";
                    $url = \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('user.dialer.profile', array_filter([
                        'number'  => $row->number_e164,
                        'contact' => $row->contact_id,
                    ])));

                    $data = array_filter([
                        'message'     => $message,
                        'number'      => $row->number_e164,
                        'contact_id'  => $row->contact_id,
                        'note'        => $row->note,
                        'callback_id' => $row->id,
                        'url'         => $url,
                    ], fn ($v) => $v !== null && $v !== '');

                    $notifications->notify($user, 'dialer.callback_due', $data);
                    $notifications->pushToUser($user, 'dialer.callback_due', 'Call-back reminder', $message, $data);

                    $row->callback_notified_at = now();
                    $row->save();
                    $sent++;
                }
            });

        $this->info("Delivered {$sent} dialer call-back reminders.");
        return self::SUCCESS;
    }
}
