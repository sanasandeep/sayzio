<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;

/**
 * Caller-ID reachability gate for the Dialer.
 *
 * The Dialer enriches a phone number with the matched Sayzio creator's name /
 * handle / biolink (via `contact.biolink_user_id` + verified linked-identifier
 * phone resolution). Neither of those resolution paths checks whether the
 * searcher can still reach that creator, so this gate is the single source of
 * truth that keeps caller-ID from naming an account that:
 *
 *   - has since been suspended/deactivated (`status != active`), or
 *   - has blocked the searcher (`UserBlock` where `blocked_user_id` = searcher).
 *
 * It mirrors the reachability gate DialerSearch applies to its People /
 * Followed groups (self is always exempt from the status check, and the block
 * check is directional — only "they blocked me" removes an account).
 */
class DialerReachability
{
    /**
     * Return the matched creator only when the searcher can reach them right
     * now; otherwise null, so the caller-ID surface simply shows no biolink.
     */
    public static function enrichableCreator(?int $searcherId, ?User $creator): ?User
    {
        if (!$creator) {
            return null;
        }

        return self::reaches($searcherId, $creator) ? $creator : null;
    }

    /**
     * Whether the searcher can reach $creator for caller-ID enrichment.
     */
    public static function reaches(?int $searcherId, ?User $creator): bool
    {
        if (!$creator) {
            return false;
        }

        $isSelf = $searcherId !== null && $creator->id === $searcherId;

        // Suspended/deactivated accounts are unreachable (mirrors the login
        // guard `($user->status ?? 'active') !== 'active'`); self is exempt so
        // a user can always resolve their own number.
        if (!$isSelf && ($creator->status ?? 'active') !== 'active') {
            return false;
        }

        // An account that has blocked the searcher must never enrich caller-ID.
        if ($searcherId !== null && !$isSelf) {
            $blocked = UserBlock::where('blocker_user_id', $creator->id)
                ->where('blocked_user_id', $searcherId)
                ->exists();
            if ($blocked) {
                return false;
            }
        }

        return true;
    }

    /**
     * Batch variant of {@see reaches()} for caller-ID LIST renders (recents /
     * frequent). Given a set of candidate creators — already loaded so their
     * status lives in memory — return `[creatorId => bool reachable]` using a
     * SINGLE `UserBlock` query for the whole set, never one query per creator.
     *
     * Mirrors how the search path pre-fetches subscribers once
     * (.agents/memory/dialer-search-scaling.md) so a growing history / contact
     * book can't turn caller-ID gating into an N+1
     * (.agents/memory/dialer-callerid-reachability.md). The result is exactly
     * consistent with calling `reaches()` per creator.
     *
     * @param iterable<User> $creators
     * @return array<int,bool>
     */
    public static function reachableMap(?int $searcherId, iterable $creators): array
    {
        $byId = [];
        foreach ($creators as $creator) {
            if ($creator) {
                $byId[$creator->id] = $creator;
            }
        }
        if (empty($byId)) {
            return [];
        }

        // ONE query for the whole batch: which of these creators have blocked
        // the searcher. Self can never block self, so it needs no special case.
        $blocked = [];
        if ($searcherId !== null) {
            $blocked = array_flip(
                UserBlock::whereIn('blocker_user_id', array_keys($byId))
                    ->where('blocked_user_id', $searcherId)
                    ->pluck('blocker_user_id')
                    ->all()
            );
        }

        $out = [];
        foreach ($byId as $id => $creator) {
            $isSelf = $searcherId !== null && $id === $searcherId;

            if (!$isSelf && ($creator->status ?? 'active') !== 'active') {
                $out[$id] = false;
                continue;
            }
            if (!$isSelf && $searcherId !== null && isset($blocked[$id])) {
                $out[$id] = false;
                continue;
            }
            $out[$id] = true;
        }

        return $out;
    }
}
