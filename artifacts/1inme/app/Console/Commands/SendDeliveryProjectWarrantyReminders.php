<?php

namespace App\Console\Commands;

use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Task #3564 — warranty reminders for Delivery Projects.
 *
 * For every project with a configured warranty window we fire two once-only
 * notifications to the project creator:
 *   1. a "warranty ending soon" heads-up `warranty_reminder_days` days before
 *      the expiry date (default 7), and
 *   2. a "warranty expired" notice on/after the expiry date.
 *
 * Dedupe is by the persisted `warranty_reminder_sent_at` /
 * `warranty_expired_notified_at` stamps, so reruns are idempotent (mirrors the
 * FeatureLaunchNotifier "stamp, never delete" pattern rather than a per-day
 * window like SendTaskDueReminders). The command is workspace-timezone aware:
 * run hourly by the scheduler, it only does work at 8 AM local time.
 */
class SendDeliveryProjectWarrantyReminders extends Command
{
    protected $signature = 'delivery-projects:warranty-reminders {--force : Run for all workspaces regardless of local hour}';
    protected $description = 'Notify creators before and on Delivery Project warranty expiry (workspace-tz aware).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $sent  = 0;

        Workspace::query()
            ->with('owner:id,timezone')
            ->whereIn('id', DeliveryProject::query()
                ->withoutGlobalScope('workspace')
                ->whereNotNull('warranty_expires_at')
                ->where(function ($q) {
                    $q->whereNull('warranty_reminder_sent_at')
                      ->orWhereNull('warranty_expired_notified_at');
                })
                ->distinct()
                ->pluck('workspace_id'))
            ->chunk(100, function ($workspaces) use ($force, &$sent) {
                foreach ($workspaces as $ws) {
                    $tz = \App\Support\PlatformTimezone::forUser($ws->owner);
                    $nowLocal = Carbon::now($tz);

                    if (!$force && $nowLocal->hour !== 8) continue;

                    $sent += $this->processWorkspace($ws->id, $nowLocal);
                }
            });

        $this->info("Sent {$sent} delivery-project warranty notifications.");
        return self::SUCCESS;
    }

    private function processWorkspace(int $workspaceId, Carbon $nowLocal): int
    {
        $sent = 0;
        $todayLocalDate = $nowLocal->toDateString();

        DeliveryProject::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('warranty_expires_at')
            ->where(function ($q) {
                $q->whereNull('warranty_reminder_sent_at')
                  ->orWhereNull('warranty_expired_notified_at');
            })
            ->with('creator:id')
            ->chunk(200, function ($projects) use ($todayLocalDate, &$sent) {
                foreach ($projects as $project) {
                    $userId = $project->created_by_user_id;
                    if (!$userId) continue;

                    $expiryDate = $project->warranty_expires_at->toDateString();
                    $leadDays   = (int) ($project->warranty_reminder_days ?? 0);
                    $remindOn   = Carbon::parse($expiryDate)->subDays(max(0, $leadDays))->toDateString();

                    // 1) Expired notice takes priority once we're on/after expiry.
                    if ($todayLocalDate >= $expiryDate) {
                        if (!$project->warranty_expired_notified_at) {
                            $this->notify($userId, $project, 'delivery_project_warranty_expired',
                                'Warranty expired: ' . $project->title, $expiryDate);
                            $project->warranty_expired_notified_at = now();
                            // Suppress a now-pointless "ending soon" reminder.
                            $project->warranty_reminder_sent_at = $project->warranty_reminder_sent_at ?: now();
                            $project->save();
                            $sent++;
                        }
                        continue;
                    }

                    // 2) Ending-soon reminder once we reach the lead-time date.
                    if (!$project->warranty_reminder_sent_at && $todayLocalDate >= $remindOn) {
                        $this->notify($userId, $project, 'delivery_project_warranty_ending',
                            'Warranty ending soon: ' . $project->title, $expiryDate);
                        $project->warranty_reminder_sent_at = now();
                        $project->save();
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function notify(int $userId, DeliveryProject $project, string $type, string $message, string $expiryDate): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'type'    => $type,
            'data'    => [
                'message'     => $message,
                'project_id'  => $project->id,
                'expires_at'  => $expiryDate,
                'url'         => route('user.delivery-projects.show', $project->id),
            ],
            'created_at' => now(),
        ]);
    }
}
