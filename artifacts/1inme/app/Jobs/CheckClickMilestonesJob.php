<?php

namespace App\Jobs;

use App\Modules\User\Models\InboxForwardDestination;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After a batch of clicks is persisted, check whether any click-milestone
 * thresholds have been crossed for the affected links and fan out webhook/
 * email notifications to matching InboxForwardDestinations.
 *
 * Each (link × destination × threshold) fires AT MOST ONCE, guaranteed by
 * an insertOrIgnore into `link_click_milestone_fires` before delivery.
 */
class CheckClickMilestonesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [15, 60, 300];
    }

    /**
     * @param int[] $linkIds  IDs of links that received clicks in the batch.
     */
    public function __construct(public array $linkIds)
    {
    }

    public function handle(): void
    {
        if (empty($this->linkIds) || !Schema::hasTable('link_click_milestone_fires')) {
            return;
        }

        $forwarder = app(InboxForwarder::class);

        foreach (array_unique($this->linkIds) as $linkId) {
            $link = Link::find($linkId);
            if (!$link) {
                continue;
            }

            $this->checkForLink($link, $forwarder);
        }
    }

    private function checkForLink(Link $link, InboxForwarder $forwarder): void
    {
        $userId = $link->user_id;

        // Compute the best estimate of the current click total:
        // committed total_clicks + any pending counter_deltas not yet flushed.
        $committed = (int) $link->total_clicks;
        $pending   = 0;
        if (Schema::hasTable('counter_deltas')) {
            $pending = (int) DB::table('counter_deltas')
                ->where('entity_type', 'link')
                ->where('entity_id', $link->id)
                ->sum('total_delta');
        }
        $currentTotal = $committed + $pending;
        if ($currentTotal <= 0) {
            return;
        }

        // Fetch all active destinations for this user that have milestone thresholds
        // AND match the click_milestone source filter.
        $destinations = InboxForwardDestination::where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->filter(fn ($d) => $d->matchesSource(InboxAggregator::SOURCE_CLICK_MILESTONE)
                              && !empty($d->clickMilestoneThresholds()));

        if ($destinations->isEmpty()) {
            return;
        }

        foreach ($destinations as $dest) {
            foreach ($dest->clickMilestoneThresholds() as $threshold) {
                if ($currentTotal < $threshold) {
                    continue;
                }

                // Idempotency: only fire once per (link, destination, threshold).
                $inserted = DB::table('link_click_milestone_fires')->insertOrIgnore([[
                    'link_id'        => $link->id,
                    'destination_id' => $dest->id,
                    'threshold'      => $threshold,
                    'fired_at'       => now(),
                ]]);

                if ($inserted === 0) {
                    continue;
                }

                try {
                    $forwarder->dispatchForClickMilestone($userId, $link, $threshold, $currentTotal, $dest);
                } catch (\Throwable $e) {
                    logger()->warning("Click milestone dispatch error (link={$link->id} threshold={$threshold}): " . $e->getMessage());
                }
            }
        }
    }
}
