<?php

namespace App\Console\Commands;

use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends per-assignee reminders for any open card whose due_date is today or
 * already past. The command is *workspace-timezone aware*: when run hourly
 * by the scheduler, it only fires for workspaces whose **local** time is
 * currently 8 AM. We dedupe per assignee per card per local calendar day so
 * reruns within the same day are idempotent.
 *
 * The workspace's tz is taken from its owner's User.timezone field (we do
 * not have a separate workspaces.timezone column today). Pass --force to
 * bypass the 8-AM gate (used by the test suite and ad-hoc CLI runs).
 */
class SendTaskDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders {--force : Run for all workspaces regardless of local hour}';
    protected $description = 'Notify task assignees about cards due today or overdue (workspace-tz aware).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $sent  = 0;

        // Pull every workspace that has at least one open, dated card so we
        // don't iterate dormant accounts. Eager-load the owner so we can
        // resolve a timezone without an N+1.
        Workspace::query()
            ->with('owner:id,timezone')
            ->whereIn('id', TaskCard::query()
                ->whereNull('completed_at')
                ->whereNull('archived_at')
                ->whereNotNull('due_date')
                ->distinct()
                ->pluck('workspace_id'))
            ->chunk(100, function ($workspaces) use ($force, &$sent) {
                foreach ($workspaces as $ws) {
                    $tz = optional($ws->owner)->timezone ?: 'UTC';
                    $nowLocal = Carbon::now($tz);

                    // Schedule fires hourly; only do real work at 8 AM local.
                    if (!$force && $nowLocal->hour !== 8) continue;

                    $todayLocal = $nowLocal->copy()->startOfDay();
                    $sent += $this->processWorkspace($ws->id, $tz, $todayLocal);
                }
            });

        $this->info("Sent {$sent} task due reminders.");
        return self::SUCCESS;
    }

    private function processWorkspace(int $workspaceId, string $tz, Carbon $todayLocal): int
    {
        $sent = 0;
        // Compare due_date as a calendar date in the workspace's local tz.
        $todayLocalDate = $todayLocal->toDateString();

        // Convert the workspace's local calendar day into a UTC window so the
        // dedupe query against `created_at` (stored as UTC by Eloquent) is
        // timezone-correct for any owner offset, including UTC+14 / UTC-11.
        $localDayStartUtc = $todayLocal->copy()->startOfDay()->timezone('UTC');
        $localDayEndUtc   = $todayLocal->copy()->endOfDay()->timezone('UTC');

        TaskCard::query()
            ->where('workspace_id', $workspaceId)
            ->whereNull('completed_at')
            ->whereNull('archived_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $todayLocalDate)
            ->with(['assignees:id', 'board:id,name'])
            ->chunk(200, function ($cards) use ($todayLocalDate, $tz, $localDayStartUtc, $localDayEndUtc, &$sent) {
                foreach ($cards as $card) {
                    $isOverdue = Carbon::parse($card->due_date, $tz)->toDateString() < $todayLocalDate;
                    $type      = $isOverdue ? 'task_overdue' : 'task_due';

                    foreach ($card->assignees as $u) {
                        // Per-assignee, per-card, per-LOCAL-day dedupe (UTC window).
                        $alreadyToday = UserNotification::where('user_id', $u->id)
                            ->whereIn('type', ['task_due', 'task_overdue'])
                            ->whereBetween('created_at', [$localDayStartUtc, $localDayEndUtc])
                            ->where('data->card_id', $card->id)
                            ->exists();
                        if ($alreadyToday) continue;

                        UserNotification::create([
                            'user_id' => $u->id,
                            'type'    => $type,
                            'data'    => [
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

        return $sent;
    }
}
