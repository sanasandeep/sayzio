<?php

namespace App\Console\Commands;

use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * Once an hour: find inbox threads whose SLA has passed and the user
 * hasn't been pinged about yet, and fire a single notification per thread
 * via the existing UserNotification pipeline (in-app + email when enabled).
 */
class CheckInboxSlaBreaches extends Command
{
    protected $signature = 'inbox:check-sla';
    protected $description = 'Notify owners about overdue inbox threads (SLA breached).';

    public function handle(): int
    {
        $rows = InboxThread::query()
            ->where('status', 'open')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->where('sla_overdue_notified', false)
            ->limit(500)
            ->get();

        $sent = 0;
        foreach ($rows as $t) {
            UserNotification::create([
                'user_id'    => $t->assignee_user_id ?: $t->user_id,
                'type'       => 'inbox.sla_overdue',
                'data'       => [
                    'thread_id'   => $t->id,
                    'subject'     => $t->subject,
                    'sender_name' => $t->sender_name,
                    'category'    => $t->category,
                    'channel'     => $t->channel,
                    'sla_due_at'  => optional($t->sla_due_at)->toIso8601String(),
                    'url'         => route('user.inbox.unified.show', $t->id),
                ],
                'created_at' => now(),
            ]);
            $t->update(['sla_overdue_notified' => true]);
            $sent++;
        }

        $this->info("Fired {$sent} SLA overdue notifications.");
        return self::SUCCESS;
    }
}
