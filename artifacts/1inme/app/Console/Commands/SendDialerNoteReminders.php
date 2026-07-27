<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\DialerNote;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;

/**
 * Task #5508: due-reminder alerts for dialer notes / to-dos.
 *
 * A note with a `remind_at` in the past, no `reminder_sent_at` stamp and not
 * yet done gets one alert delivered to its owner AND to every account the
 * note is shared with (in-app + push + email per preference), then the row
 * is stamped so reruns are idempotent — same pattern as the call-back
 * reminder command.
 */
class SendDialerNoteReminders extends Command
{
    protected $signature = 'dialer:send-note-reminders';
    protected $description = 'Deliver due dialer note/to-do reminders (in-app + push + email), once each.';

    public function handle(NotificationService $notifications): int
    {
        $sent = 0;

        DialerNote::query()
            ->whereNotNull('remind_at')
            ->whereNull('reminder_sent_at')
            ->where('remind_at', '<=', now())
            ->where('done', false)
            ->with('shares')
            ->orderBy('remind_at')
            ->chunkById(200, function ($notes) use ($notifications, &$sent) {
                foreach ($notes as $note) {
                    $recipients = collect([$note->user_id])
                        ->merge($note->shares->pluck('shared_with_user_id'))
                        ->filter()
                        ->unique()
                        ->values();

                    $title = $note->title ?: ($note->kind === 'checklist' ? 'To-do reminder' : 'Note reminder');
                    $message = $note->kind === 'checklist'
                        ? "To-do due: {$title}"
                        : "Reminder: {$title}";

                    $data = array_filter([
                        'message' => $message,
                        'note_id' => $note->id,
                        'kind' => $note->kind ?: 'note',
                        'number' => $note->number_e164,
                        'url' => \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('user.dialer.notes')),
                    ], fn ($v) => $v !== null && $v !== '');

                    foreach ($recipients as $userId) {
                        $user = User::find($userId);
                        if (!$user) continue;
                        $notifications->notify($user, 'dialer.note_due', $data);
                        $notifications->pushToUser($user, 'dialer.note_due', 'Reminder', $message, $data);
                    }

                    $note->reminder_sent_at = now();
                    $note->save();
                    $sent++;
                }
            });

        $this->info("Delivered {$sent} dialer note reminders.");
        return self::SUCCESS;
    }
}
