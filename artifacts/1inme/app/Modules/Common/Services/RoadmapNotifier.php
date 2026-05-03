<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\RoadmapVote;
use App\Modules\User\Models\User;

/**
 * Notifies everyone who cared about a roadmap item when the creator
 * ships it. Two delivery paths are fanned out together:
 *
 *  - In-app inbox notifications for upvoters who were signed in (we
 *    have a `viewer_user_id` on their vote). These honor the user's
 *    notification preferences via NotificationService.
 *  - Branded transactional emails for upvoters/submitters who left
 *    an email but no account. We dedupe by lowercased email so a
 *    user who voted twice from different browsers only gets one.
 *
 * The total returned reflects successful deliveries across BOTH
 * paths, so the triage UI can show "notified N upvoter(s)".
 */
class RoadmapNotifier
{
    public function __construct(private NotificationService $notifications) {}

    public function notifyShipped(RoadmapItem $item): int
    {
        $alias = $item->link?->alias ?? '';
        $url   = $alias ? url('/' . $alias) : null;
        $sent  = 0;

        // 1) In-app: signed-in voters
        $voterIds = RoadmapVote::where('item_id', $item->id)
            ->whereNotNull('viewer_user_id')
            ->pluck('viewer_user_id')
            ->unique()
            ->all();

        $notifiedUserEmails = [];
        if (!empty($voterIds)) {
            foreach (User::whereIn('id', $voterIds)->get() as $user) {
                $delivered = $this->notifications->notify($user, 'roadmap_idea_shipped', [
                    'message'    => 'Your idea just went live: ' . $item->title,
                    'item_id'    => $item->id,
                    'link_alias' => $alias,
                    'title'      => $item->title,
                    'url'        => $url,
                ]);
                if ($delivered) $sent++;
                if ($user->email) $notifiedUserEmails[] = strtolower(trim($user->email));
            }
        }

        // 2) Email: every distinct address attached to the item via
        //    submitter_email + roadmap_votes.email, minus addresses we
        //    already reached through the in-app channel above.
        $voterEmails = RoadmapVote::where('item_id', $item->id)
            ->whereNotNull('email')->where('email', '!=', '')
            ->pluck('email')->all();
        $candidates = collect(array_merge($voterEmails, [$item->submitter_email]))
            ->filter()
            ->map(fn ($e) => strtolower(trim($e)))
            ->unique()
            ->reject(fn ($e) => in_array($e, $notifiedUserEmails, true))
            ->values();

        foreach ($candidates as $email) {
            if (RoadmapShippedMail::dispatchFor($item, $email)) {
                $sent++;
            }
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
