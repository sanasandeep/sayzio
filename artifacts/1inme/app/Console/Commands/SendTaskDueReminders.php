<?php

namespace App\Console\Commands;

use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\UserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends a single notification per assignee for any open card whose due_date
 * is today or already past. We dedupe on the same calendar day so a card
 * doesn't spam its assignees if the command runs more than once per day.
 */
class SendTaskDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders';
    protected $description = 'Notify task assignees about cards due today or overdue.';

    public function handle(): int
    {
        $today = Carbon::today();
        $sent = 0;

        // Cross-workspace pull: scheduler runs in CLI with no
        // current_workspace bound, so the global scope is naturally skipped.
        TaskCard::query()
            ->whereNull('completed_at')
            ->whereNull('archived_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today)
            ->with(['assignees:id', 'board:id,name'])
            ->chunk(200, function ($cards) use ($today, &$sent) {
                foreach ($cards as $card) {
                    foreach ($card->assignees as $u) {
                        $isOverdue = Carbon::parse($card->due_date)->lt($today);
                        $type = $isOverdue ? 'task_overdue' : 'task_due';
                        // Dedupe: skip if this assignee already got a reminder
                        // for this card today.
                        $alreadyToday = UserNotification::where('user_id', $u->id)
                            ->whereIn('type', ['task_due', 'task_overdue'])
                            ->whereDate('created_at', $today)
                            ->where('data->card_id', $card->id)
                            ->exists();
                        if ($alreadyToday) continue;

                        UserNotification::create([
                            'user_id'    => $u->id,
                            'type'       => $type,
                            'data'       => [
                                'message'    => $isOverdue
                                    ? 'Overdue: ' . $card->title
                                    : 'Due today: ' . $card->title,
                                'card_id'    => $card->id,
                                'board_id'   => $card->board_id,
                                'board_name' => optional($card->board)->name,
                                'due_date'   => optional($card->due_date)->toDateString(),
                                'url'        => route('user.tasks.show', $card->board_id) . '#card-' . $card->id,
                            ],
                            'created_at' => now(),
                        ]);
                        $sent++;
                    }
                }
            });

        $this->info("Sent {$sent} task due reminders.");
        return self::SUCCESS;
    }
}
