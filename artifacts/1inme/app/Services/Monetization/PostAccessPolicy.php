<?php

namespace App\Services\Monetization;

use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\PostUnlock;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;

/**
 * Single source of truth for "can this viewer see this post in full?".
 *
 * - Free posts                : everyone can see them.
 * - Tier-gated posts          : creator + any fan with a current subscription
 *                                to a tier whose sort_order >= the lowest
 *                                tier in the post's visible_tier_ids list.
 * - Pay-per-view posts        : creator + any fan with a non-refunded
 *                                PostUnlock row.
 *
 * The policy returns a structured result so the renderer can decide
 * between the full body, the blurred preview, or a marker of "you
 * already paid" — without re-querying.
 */
class PostAccessPolicy
{
    public const REASON_OWNER       = 'owner';
    public const REASON_FREE        = 'free';
    public const REASON_SUBSCRIBER  = 'subscriber';
    public const REASON_PPV         = 'ppv_unlocked';
    public const REASON_TIER_LOCKED = 'tier_locked';
    public const REASON_PPV_LOCKED  = 'ppv_locked';
    public const REASON_GUEST       = 'guest';

    /**
     * @return array{can:bool, reason:string, requires_subscription:bool, requires_ppv:bool, lowest_tier?:?\App\Modules\User\Models\SubscriptionTier}
     */
    public static function evaluate(?User $viewer, CreatorPost $post): array
    {
        $isOwner = $viewer && (int) $viewer->id === (int) $post->user_id;
        if ($isOwner) {
            return ['can' => true, 'reason' => self::REASON_OWNER, 'requires_subscription' => false, 'requires_ppv' => false];
        }
        if (!$post->isPaywalled()) {
            return ['can' => true, 'reason' => self::REASON_FREE, 'requires_subscription' => false, 'requires_ppv' => false];
        }

        if ($post->visibility === CreatorPost::VISIBILITY_TIER) {
            $tierIds = is_array($post->visible_tier_ids) ? array_map('intval', $post->visible_tier_ids) : [];
            $lowest  = $tierIds
                ? SubscriptionTier::query()->whereIn('id', $tierIds)->orderBy('sort_order')->first()
                : null;

            if ($viewer) {
                $sub = CreatorSubscription::query()
                    ->where('fan_user_id', $viewer->id)
                    ->where('creator_user_id', $post->user_id)
                    ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING, CreatorSubscription::STATUS_PAST_DUE])
                    ->first();
                if ($sub && $sub->isCurrent() && $lowest) {
                    $fanTier = SubscriptionTier::find($sub->tier_id);
                    if ($fanTier && (int) $fanTier->sort_order >= (int) $lowest->sort_order) {
                        return ['can' => true, 'reason' => self::REASON_SUBSCRIBER, 'requires_subscription' => false, 'requires_ppv' => false, 'lowest_tier' => $lowest];
                    }
                }
            }
            return [
                'can' => false,
                'reason' => $viewer ? self::REASON_TIER_LOCKED : self::REASON_GUEST,
                'requires_subscription' => true,
                'requires_ppv' => false,
                'lowest_tier' => $lowest,
            ];
        }

        if ($post->visibility === CreatorPost::VISIBILITY_PPV) {
            if ($viewer) {
                $unlock = PostUnlock::query()
                    ->where('post_id', $post->id)
                    ->where('fan_user_id', $viewer->id)
                    ->whereNull('refunded_at')
                    ->first();
                if ($unlock) {
                    return ['can' => true, 'reason' => self::REASON_PPV, 'requires_subscription' => false, 'requires_ppv' => false];
                }
            }
            return [
                'can' => false,
                'reason' => $viewer ? self::REASON_PPV_LOCKED : self::REASON_GUEST,
                'requires_subscription' => false,
                'requires_ppv' => true,
            ];
        }

        return ['can' => false, 'reason' => self::REASON_TIER_LOCKED, 'requires_subscription' => true, 'requires_ppv' => false];
    }

    /**
     * Bulk evaluator for a feed page. Avoids issuing one DB query per
     * post when rendering /@handle's public timeline.
     */
    public static function evaluateMany(?User $viewer, iterable $posts): array
    {
        $byPost = [];

        $tierPostIds = [];
        $ppvPostIds  = [];
        $allTierIds  = [];
        foreach ($posts as $p) {
            if ($p->visibility === CreatorPost::VISIBILITY_TIER) {
                $tierPostIds[] = (int) $p->id;
                if (is_array($p->visible_tier_ids)) {
                    foreach ($p->visible_tier_ids as $t) $allTierIds[] = (int) $t;
                }
            } elseif ($p->visibility === CreatorPost::VISIBILITY_PPV) {
                $ppvPostIds[] = (int) $p->id;
            }
        }

        $tiersById = collect();
        if ($allTierIds) {
            $tiersById = SubscriptionTier::query()->whereIn('id', array_unique($allTierIds))->get()->keyBy('id');
        }

        $fanSubByCreator = collect();
        $unlockedPostIds = [];
        if ($viewer) {
            $creatorIds = collect($posts)->pluck('user_id')->unique()->all();
            $fanSubByCreator = CreatorSubscription::query()
                ->where('fan_user_id', $viewer->id)
                ->whereIn('creator_user_id', $creatorIds)
                ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING, CreatorSubscription::STATUS_PAST_DUE])
                ->get()->keyBy('creator_user_id');

            if ($ppvPostIds) {
                $unlockedPostIds = PostUnlock::query()
                    ->where('fan_user_id', $viewer->id)
                    ->whereIn('post_id', $ppvPostIds)
                    ->whereNull('refunded_at')
                    ->pluck('post_id')->all();
            }
        }
        $unlockedPostIds = array_flip($unlockedPostIds);

        foreach ($posts as $p) {
            $isOwner = $viewer && (int) $viewer->id === (int) $p->user_id;
            if ($isOwner) {
                $byPost[$p->id] = ['can' => true, 'reason' => self::REASON_OWNER, 'requires_subscription' => false, 'requires_ppv' => false];
                continue;
            }
            if (!$p->isPaywalled()) {
                $byPost[$p->id] = ['can' => true, 'reason' => self::REASON_FREE, 'requires_subscription' => false, 'requires_ppv' => false];
                continue;
            }
            if ($p->visibility === CreatorPost::VISIBILITY_TIER) {
                $ids = is_array($p->visible_tier_ids) ? array_map('intval', $p->visible_tier_ids) : [];
                $lowest = null;
                foreach ($ids as $id) {
                    $t = $tiersById->get($id);
                    if ($t && (!$lowest || (int) $t->sort_order < (int) $lowest->sort_order)) $lowest = $t;
                }
                $sub = $fanSubByCreator->get($p->user_id);
                if ($sub && $sub->isCurrent() && $lowest) {
                    $fanTier = $tiersById->get($sub->tier_id) ?? SubscriptionTier::find($sub->tier_id);
                    if ($fanTier && (int) $fanTier->sort_order >= (int) $lowest->sort_order) {
                        $byPost[$p->id] = ['can' => true, 'reason' => self::REASON_SUBSCRIBER, 'requires_subscription' => false, 'requires_ppv' => false, 'lowest_tier' => $lowest];
                        continue;
                    }
                }
                $byPost[$p->id] = [
                    'can' => false,
                    'reason' => $viewer ? self::REASON_TIER_LOCKED : self::REASON_GUEST,
                    'requires_subscription' => true,
                    'requires_ppv' => false,
                    'lowest_tier' => $lowest,
                ];
            } else { // PPV
                if (isset($unlockedPostIds[$p->id])) {
                    $byPost[$p->id] = ['can' => true, 'reason' => self::REASON_PPV, 'requires_subscription' => false, 'requires_ppv' => false];
                } else {
                    $byPost[$p->id] = [
                        'can' => false,
                        'reason' => $viewer ? self::REASON_PPV_LOCKED : self::REASON_GUEST,
                        'requires_subscription' => false,
                        'requires_ppv' => true,
                    ];
                }
            }
        }

        return $byPost;
    }

    /**
     * Strip the post array down to the safe fields a locked viewer
     * can see. Body is replaced with a teaser excerpt; media URLs are
     * dropped so direct-link bypass is impossible.
     */
    public static function maskForLockedViewer(array $payload, CreatorPost $post): array
    {
        $payload['locked']           = true;
        $payload['blur_intensity']   = $post->blurIntensity();
        $payload['teaser_caption']   = $post->teaserCaption();
        // Body excerpt only.
        $body = (string) ($payload['body'] ?? '');
        $payload['body_excerpt']     = mb_substr($body, 0, 220);
        $payload['body']             = null;
        // Drop heavy media URLs so the client can't fetch them directly.
        $payload['image']            = null;
        $payload['media']            = null;

        // Creator-controlled paywall preview (Task #1209). The composer
        // lets the creator opt in to revealing the first N gallery items
        // or the video poster as a teaser; default is none. We expose
        // ONLY the asset URLs the creator explicitly opted to share —
        // never the body, the locked items, or the video file itself.
        $type      = $post->effectiveType();
        $mediaArr  = is_array($post->media) ? $post->media : [];
        $galleryN  = $post->galleryPreviewCount();
        $videoSecs = $post->videoPreviewSeconds();
        $preview   = null;

        if ($type === CreatorPost::TYPE_GALLERY && $galleryN > 0 && !empty($mediaArr['items'])) {
            $items = array_slice(array_values($mediaArr['items']), 0, $galleryN);
            $preview = [
                'kind'         => 'gallery',
                'items'        => array_values(array_map(
                    fn ($it) => ['url' => $it['url'] ?? null, 'alt' => $it['alt'] ?? null],
                    $items
                )),
                'total_items'  => count($mediaArr['items']),
                'visible_count'=> count($items),
            ];
        } elseif ($type === CreatorPost::TYPE_VIDEO && $videoSecs > 0 && !empty($mediaArr['poster'])) {
            $preview = [
                'kind'    => 'video',
                'poster'  => $mediaArr['poster'],
                'seconds' => $videoSecs,
            ];
        }
        $payload['preview'] = $preview;
        $payload['paywall_preview'] = [
            'gallery_preview_count' => $galleryN,
            'video_preview_seconds' => $videoSecs,
        ];
        return $payload;
    }
}
