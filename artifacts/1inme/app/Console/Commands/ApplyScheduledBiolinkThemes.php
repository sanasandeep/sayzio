<?php

namespace App\Console\Commands;

use App\Modules\User\Models\BiolinkThemeSchedule;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\BiolinkThemeResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-minute cron that activates due biolink theme schedules and
 * reverts ones whose window has ended. Idempotent — safe to re-run.
 */
class ApplyScheduledBiolinkThemes extends Command
{
    protected $signature = 'biolinks:apply-scheduled-themes';

    protected $description = 'Activate / revert biolink theme schedules whose start or end time has elapsed.';

    public function handle(BiolinkThemeResolver $resolver): int
    {
        $now = now();
        $activated = 0;
        $reverted  = 0;

        // 1. Revert any active schedules whose window has ended. We do
        //    this BEFORE activating new ones so a back-to-back hand-off
        //    (one ending, another starting at the same minute) lands on
        //    the new theme rather than the previous baseline.
        $expiring = BiolinkThemeSchedule::query()
            ->where('status', BiolinkThemeSchedule::STATUS_ACTIVE)
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($expiring as $sched) {
            try {
                DB::transaction(function () use ($sched) {
                    $link = Link::query()->lockForUpdate()->find($sched->link_id);
                    if (!$link) return;

                    $settings = $link->settings ?? [];
                    $prev     = (array) ($sched->prev_settings ?? []);
                    $current  = (array) ($settings['biolink'] ?? []);

                    // Restore the themable keys back to the snapshot we
                    // captured at activation, but preserve anything the
                    // creator changed on non-themable keys mid-window.
                    foreach (BiolinkThemeResolver::THEMABLE_KEYS as $k) {
                        if (array_key_exists($k, $prev)) {
                            $current[$k] = $prev[$k];
                        } else {
                            unset($current[$k]);
                        }
                    }
                    $settings['biolink'] = $current;
                    $link->settings = $settings;
                    $link->save();

                    $sched->status      = BiolinkThemeSchedule::STATUS_COMPLETED;
                    $sched->reverted_at = now();
                    $sched->save();
                });
                $reverted++;
            } catch (\Throwable $e) {
                Log::warning('biolink theme revert failed', [
                    'schedule_id' => $sched->id,
                    'link_id'     => $sched->link_id,
                    'err'         => $e->getMessage(),
                ]);
            }
        }

        // 2. Activate pending schedules whose start time has elapsed
        //    (and whose end is still in the future — anything fully in
        //    the past at this point gets marked completed without ever
        //    being applied so it doesn't keep getting picked up).
        $due = BiolinkThemeSchedule::query()
            ->where('status', BiolinkThemeSchedule::STATUS_PENDING)
            ->where('starts_at', '<=', $now)
            ->get();

        foreach ($due as $sched) {
            if ($sched->ends_at && $sched->ends_at->isPast()) {
                $sched->status      = BiolinkThemeSchedule::STATUS_COMPLETED;
                $sched->applied_at  = $sched->applied_at ?: now();
                $sched->reverted_at = $sched->reverted_at ?: now();
                $sched->save();
                continue;
            }

            try {
                DB::transaction(function () use ($sched, $resolver) {
                    $link = Link::query()->lockForUpdate()->find($sched->link_id);
                    if (!$link || $link->type !== 'biolink') return;

                    $theme = $sched->theme()->first();
                    if (!$theme) {
                        $sched->status = BiolinkThemeSchedule::STATUS_CANCELLED;
                        $sched->save();
                        return;
                    }

                    $settings = $link->settings ?? [];
                    $current  = (array) ($settings['biolink'] ?? []);

                    // Snapshot BEFORE merging so revert restores the
                    // exact look the page had right before activation.
                    $sched->prev_settings = $resolver->snapshotFromLink($link);

                    $themeSettings = (array) ($theme->settings ?? []);
                    $settings['biolink'] = array_replace($current, $themeSettings);
                    $link->settings = $settings;
                    $link->save();

                    $sched->status     = BiolinkThemeSchedule::STATUS_ACTIVE;
                    $sched->applied_at = now();
                    $sched->save();
                });
                $activated++;
            } catch (\Throwable $e) {
                Log::warning('biolink theme activate failed', [
                    'schedule_id' => $sched->id,
                    'link_id'     => $sched->link_id,
                    'err'         => $e->getMessage(),
                ]);
            }
        }

        $this->info("Activated {$activated}, reverted {$reverted}.");
        return self::SUCCESS;
    }
}
