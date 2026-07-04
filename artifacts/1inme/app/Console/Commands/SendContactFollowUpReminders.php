<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Delivers contact/lead follow-up reminders that have come due (Task #3524).
 * A reminder is a `contacts` row with `follow_up_at` in the past and no
 * `follow_up_notified_at` stamp yet. Delivered in-app + email + push
 * (honoring per-channel notification preferences) exactly once, then the row
 * is stamped so reruns are idempotent and it is never re-sent.
 *
 * Timezone-aware: `follow_up_at` is stored as a timestamptz, so the UTC
 * comparison against `now()` fires at the exact instant the user picked
 * regardless of which timezone they set it from.
 *
 * Runs every five minutes from the scheduler.
 */
class SendContactFollowUpReminders extends Command
{
    protected $signature = 'contacts:send-follow-up-reminders';
    protected $description = 'Deliver due contact/lead follow-up reminders (in-app + email + push), once each.';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        Contact::query()
            ->whereNotNull('follow_up_at')
            ->whereNull('follow_up_notified_at')
            ->where('follow_up_at', '<=', now())
            ->orderBy('follow_up_at')
            ->chunkById(200, function ($rows) use ($notifications, &$sent) {
                foreach ($rows as $contact) {
                    $user = User::find($contact->user_id);
                    if (!$user) {
                        // Orphaned row — stamp so we don't re-scan it forever.
                        $contact->follow_up_notified_at = now();
                        $contact->saveQuietly();
                        continue;
                    }

                    $this->notifyOwner($notifications, $user, $contact);

                    $contact->follow_up_notified_at = now();
                    $contact->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Delivered {$sent} contact follow-up reminders.");
        return self::SUCCESS;
    }

    protected function notifyOwner(NotificationService $notifications, User $user, Contact $contact): void
    {
        $who = $contact->nameForDisplay();
        $subject = "Follow-up reminder · {$who}";
        $message = "Time to follow up with {$who}" . ($contact->follow_up_note ? ': ' . $contact->follow_up_note : '.');
        $contactUrl = route('user.contacts.show', $contact);

        $data = array_filter([
            'message'    => $message,
            'contact_id' => $contact->id,
            'note'       => $contact->follow_up_note,
            'url'        => $contactUrl,
        ], fn ($v) => $v !== null && $v !== '');

        $notification = null;
        try {
            $notification = $notifications->notify($user, 'contact.follow_up_reminder', $data);
        } catch (\Throwable $e) {
            Log::warning('contact.follow_up_reminder in-app notify failed: ' . $e->getMessage());
        }

        if ($user->email && $notifications->prefersChannel($user->id, 'contact.follow_up_reminder', 'email')) {
            try {
                Emailer::send('contact.follow_up_reminder', $user->email, [
                    'contact_name' => $who,
                    'note_line'    => $contact->follow_up_note ? ("\n\nNote: " . $contact->follow_up_note) : '',
                    'contact_url'  => $contactUrl,
                ], ['user' => $user->id, 'related' => $contact]);
            } catch (\Throwable $e) {
                Log::warning('contact.follow_up_reminder email failed: ' . $e->getMessage());
            }
        }

        $notifications->pushToUser(
            $user,
            'contact.follow_up_reminder',
            $subject,
            $message,
            array_merge(
                [
                    'contact_id' => $contact->id,
                    'url'        => $contactUrl,
                ],
                $notification ? ['notification_id' => $notification->id] : [],
            ),
        );
    }
}
