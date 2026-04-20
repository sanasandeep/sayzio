<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;

/**
 * Builds the subject + view data for a follower-digest email from a
 * collection of pending UserNotification rows. Shared by the scheduled
 * SendFollowerDigest command and the on-demand "send sample" preview so
 * the wording and rendered HTML stay consistent.
 */
class FollowerDigestComposer
{
    /**
     * @param  iterable  $pending  UserNotification rows for this user
     * @return array{subject:string, viewData:array, count:int, creator_count:int}
     */
    public static function compose(User $user, iterable $pending, bool $isSample = false): array
    {
        $byCreator = [];
        $count = 0;
        foreach ($pending as $row) {
            $count++;
            $data = $row->data ?? [];
            $cid = (int) ($data['creator_id'] ?? 0);
            if (!isset($byCreator[$cid])) {
                $byCreator[$cid] = [
                    'id'       => $cid,
                    'name'     => $data['creator_name'] ?? 'A creator you follow',
                    'avatar'   => self::absoluteAvatarUrl($data['creator_avatar'] ?? null),
                    'messages' => [],
                ];
            }
            $msg = trim((string) ($data['message'] ?? ''));
            if ($msg !== '') {
                $byCreator[$cid]['messages'][] = [
                    'text'  => $msg,
                    'image' => self::absoluteAvatarUrl($data['post_image'] ?? null),
                ];
            }
        }
        $creatorCount = count($byCreator);

        // Resolve each creator's primary biolink URL for deep-link CTAs.
        $creatorIds = array_filter(array_keys($byCreator));
        $biolinkByCreator = [];
        if (!empty($creatorIds)) {
            $biolinkByCreator = Link::whereIn('user_id', $creatorIds)
                ->where('type', 'biolink')
                ->with('domain')
                ->get()
                ->groupBy('user_id')
                ->map(fn ($group) => $group->first())
                ->all();
        }

        $creators = [];
        foreach ($byCreator as $cid => $entry) {
            $shown = array_slice($entry['messages'], 0, 5);
            $extra = max(0, count($entry['messages']) - 5);
            $link  = $biolinkByCreator[$cid] ?? null;
            $creators[] = [
                'name'     => $entry['name'],
                'avatar'   => $entry['avatar'],
                'url'      => $link ? $link->getShortUrl() : null,
                'messages' => $shown,
                'extra'    => $extra,
            ];
        }

        if ($isSample) {
            $subject = "[Sample] Your daily digest preview";
        } else {
            $subject = "Your daily digest: {$count} update" . ($count === 1 ? '' : 's')
                . " from {$creatorCount} creator" . ($creatorCount === 1 ? '' : 's');
        }

        $viewData = [
            'userName'     => $user->name ?: 'there',
            'subject'      => $subject,
            'creators'     => $creators,
            'totalUpdates' => $count,
            'creatorCount' => $creatorCount,
            'isSample'     => $isSample,
        ];

        return [
            'subject'       => $subject,
            'viewData'      => $viewData,
            'count'         => $count,
            'creator_count' => $creatorCount,
        ];
    }

    /**
     * Avatars are stored as relative paths like `/storage/...`. Email clients
     * need an absolute URL, so promote relatives to absolute and pass through
     * anything that's already a full URL.
     */
    private static function absoluteAvatarUrl(?string $avatar): ?string
    {
        if (!$avatar) return null;
        $avatar = trim($avatar);
        if ($avatar === '') return null;
        if (preg_match('#^https?://#i', $avatar)) return $avatar;
        return url($avatar);
    }
}
