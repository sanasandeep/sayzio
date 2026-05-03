<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\RoadmapVote;
use App\Modules\User\Models\User;

/**
 * Sends in-app notifications to all upvoters of a roadmap item when
 * the creator marks it as shipped. Honors per-user notification
 * preferences via NotificationService::notify(); guests (votes
 * without a viewer_user_id) are silently skipped — they don't have
 * an inbox to deliver to.
 */
class RoadmapNotifier
{
    public function __construct(private NotificationService $notifications) {}

    public function notifyShipped(RoadmapItem $item): int
    {
        $voterIds = RoadmapVote::where('item_id', $item->id)
            ->whereNotNull('viewer_user_id')
            ->pluck('viewer_user_id')
            ->unique()
            ->all();
        if (empty($voterIds)) return 0;

        $alias = $item->link?->alias ?? '';
        $url = $alias ? url('/' . $alias) : null;
        $sent = 0;

        foreach (User::whereIn('id', $voterIds)->get() as $user) {
            $delivered = $this->notifications->notify($user, 'roadmap_idea_shipped', [
                'message'    => 'Your idea just went live: ' . $item->title,
                'item_id'    => $item->id,
                'link_alias' => $alias,
                'title'      => $item->title,
                'url'        => $url,
            ]);
            if ($delivered) $sent++;
        }
        return $sent;
    }

    /**
     * Notify the creator of a brand-new submission so they can triage
     * it from their workspace dashboard. Best-effort — silent failures.
     */
    public function notifyNewSubmission(RoadmapItem $item): void
    {
        $creator = $item->link?->user;
        if (!$creator) return;
        $this->notifications->notify($creator, 'roadmap_new_submission', [
            'message'    => 'New roadmap idea: ' . $item->title,
            'item_id'    => $item->id,
            'link_alias' => $item->link?->alias,
            'submitter'  => $item->submitter_name,
            'url'        => route('user.roadmap.triage', ['link' => $item->link_id]),
        ]);
    }
}
