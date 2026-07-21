<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CreatorPost extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'title', 'body', 'image', 'scheduled_at', 'published_at', 'pinned_at',
        'approval_status', 'approval_requested_at', 'approval_decided_at',
        'approval_decided_by_user_id', 'intended_scheduled_at',
        // Creator Profile post types (Task #1207).
        'post_type', 'media', 'reactions_count', 'comments_count',
        // Paywall (Task #1209).
        'visibility', 'visible_tier_ids',
        'ppv_price_cents', 'ppv_currency', 'paywall_settings',
        // Country gating + trending (Task #1211).
        'country_block_list', 'country_allow_list', 'view_count_7d',
    ];

    protected $casts = [
        'scheduled_at'          => 'datetime',
        'published_at'          => 'datetime',
        'pinned_at'             => 'datetime',
        'approval_requested_at' => 'datetime',
        'approval_decided_at'   => 'datetime',
        'intended_scheduled_at' => 'datetime',
        'media'                 => 'array',
        'reactions_count'       => 'integer',
        'comments_count'        => 'integer',
        'visible_tier_ids'      => 'array',
        'ppv_price_cents'       => 'integer',
        'paywall_settings'      => 'array',
        'country_block_list'    => 'array',
        'country_allow_list'    => 'array',
        'view_count_7d'         => 'integer',
    ];

    public const VISIBILITY_FREE = 'free';
    public const VISIBILITY_TIER = 'tier';
    public const VISIBILITY_PPV  = 'ppv';

    public const VISIBILITIES = [self::VISIBILITY_FREE, self::VISIBILITY_TIER, self::VISIBILITY_PPV];

    public function isPaywalled(): bool
    {
        return in_array($this->visibility, [self::VISIBILITY_TIER, self::VISIBILITY_PPV], true);
    }

    public function ppvPriceDollars(): float
    {
        return ((int) $this->ppv_price_cents) / 100;
    }

    /** Resolved blur intensity (low|medium|high). */
    public function blurIntensity(): string
    {
        $s = is_array($this->paywall_settings) ? $this->paywall_settings : [];
        $b = $s['blur_intensity'] ?? 'medium';
        return in_array($b, ['low', 'medium', 'high'], true) ? $b : 'medium';
    }

    public function teaserCaption(): ?string
    {
        $s = is_array($this->paywall_settings) ? $this->paywall_settings : [];
        return isset($s['teaser']) && is_string($s['teaser']) && trim($s['teaser']) !== ''
            ? trim($s['teaser']) : null;
    }

    /**
     * Number of gallery items the creator wants visible (in the clear)
     * on the locked variant of this post. 0 means "no preview, just a
     * gradient placeholder". Bounded 0-3 — we don't want to give away
     * more than three of a paid gallery for free.
     */
    public function galleryPreviewCount(): int
    {
        $s = is_array($this->paywall_settings) ? $this->paywall_settings : [];
        $n = (int) ($s['gallery_preview_count'] ?? 0);
        return max(0, min(3, $n));
    }

    /**
     * Number of seconds of a paywalled video the creator wants visible
     * on the locked variant. We don't actually trim the video file — we
     * just expose the poster image and the duration so the client can
     * render a "X-second preview available" affordance. Bounded 0-30.
     */
    public function videoPreviewSeconds(): int
    {
        $s = is_array($this->paywall_settings) ? $this->paywall_settings : [];
        $n = (int) ($s['video_preview_seconds'] ?? 0);
        return max(0, min(30, $n));
    }

    public const TYPE_TEXT    = 'text';
    public const TYPE_IMAGE   = 'image';
    public const TYPE_GALLERY = 'gallery';
    public const TYPE_VIDEO   = 'video';
    public const TYPE_AUDIO   = 'audio';
    public const TYPE_LINK    = 'link';

    public const TYPES = [
        self::TYPE_TEXT, self::TYPE_IMAGE, self::TYPE_GALLERY,
        self::TYPE_VIDEO, self::TYPE_AUDIO, self::TYPE_LINK,
    ];

    public function comments()  { return $this->hasMany(CreatorPostComment::class, 'post_id'); }
    public function reactions() { return $this->hasMany(CreatorPostReaction::class, 'post_id'); }

    /**
     * Convenient accessor that gives you the post type after defaulting
     * legacy rows (which only had an `image` column) to the right type.
     */
    public function effectiveType(): string
    {
        if (in_array($this->post_type, self::TYPES, true) && $this->post_type !== self::TYPE_TEXT) {
            return $this->post_type;
        }
        if (!empty($this->image)) return self::TYPE_IMAGE;
        return self::TYPE_TEXT;
    }

    public const APPROVAL_PENDING  = 'pending_review';
    public const APPROVAL_CHANGES  = 'changes_requested';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    public function user() { return $this->belongsTo(User::class); }

    public function cloudAttachments()
    {
        return $this->morphMany(CloudFileAttachment::class, 'attachable');
    }

    protected static function booted(): void
    {
        static::deleting(function (CreatorPost $post) {
            $post->cloudAttachments()->delete();
        });

        // Keep User.posts_count in sync regardless of which controller
        // (web, API, scheduler, approval workflow) saves the row, so the
        // /@handle stats strip and /creators directory don't have to
        // recount on every render. Counts a post once it transitions to
        // a non-null published_at, and decrements when published_at is
        // cleared or the row is deleted while published.
        static::saved(function (CreatorPost $post) {
            $original = $post->getOriginal('published_at');
            $current  = $post->published_at;
            if (!$post->user_id) return;
            if (!$original && $current) {
                User::query()->whereKey($post->user_id)->increment('posts_count');
            } elseif ($original && !$current) {
                User::query()->whereKey($post->user_id)->where('posts_count', '>', 0)->decrement('posts_count');
            }
        });

        static::deleted(function (CreatorPost $post) {
            if ($post->user_id && $post->published_at) {
                User::query()->whereKey($post->user_id)->where('posts_count', '>', 0)->decrement('posts_count');
            }
        });
    }

    public function isScheduled(): bool
    {
        return $this->published_at === null
            && $this->scheduled_at !== null
            && $this->scheduled_at->isFuture();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null && $this->isPublished();
    }

    public function isPendingReview(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function needsChanges(): bool
    {
        return $this->approval_status === self::APPROVAL_CHANGES;
    }

    public function wasRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED && !$this->isPublished();
    }

    public function statusLabel(): string
    {
        if ($this->isPendingReview())  return 'Pending review';
        if ($this->needsChanges())     return 'Changes requested';
        if ($this->wasRejected())      return 'Rejected';
        if ($this->isScheduled())      return 'Scheduled';
        if ($this->isPinned())         return 'Pinned';
        if ($this->isPublished())      return 'Published';
        return 'Draft';
    }

    public function approvalComments()
    {
        return $this->hasMany(PostApprovalComment::class)->orderBy('created_at');
    }

    public function approvalDecider()
    {
        return $this->belongsTo(User::class, 'approval_decided_by_user_id');
    }

    public function scopePublished($q)
    {
        return $q->whereNotNull('published_at');
    }

    public function scopeDueForPublish($q)
    {
        return $q->whereNull('published_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Publish all due scheduled posts (scheduled_at <= now and not yet
     * published). Optionally restrict to a creator's own posts.
     *
     * Creates the FeedEvent + debounced follower notifications at this
     * point — the same side-effects an immediate publish would have.
     */
    public static function publishDuePosts(?int $userId = null): int
    {
        $q = static::dueForPublish();
        if ($userId) $q->where('user_id', $userId);
        $due = $q->get();

        $count = 0;
        foreach ($due as $post) {
            $creator = $post->user;
            if (!$creator) {
                $post->published_at = now();
                $post->save();
                continue;
            }

            $post->published_at = now();
            $post->save();

            FeedEvent::create([
                'user_id'      => $creator->id,
                'type'         => 'post',
                'subject_id'   => $post->id,
                'subject_type' => static::class,
                'data'         => [
                    'title'          => $post->title,
                    'body_excerpt'   => mb_substr($post->body, 0, 160),
                    'creator_name'   => $creator->name,
                    'creator_avatar' => \App\Support\PublicStorageUrl::resolve($creator->creatorAvatarRaw()),
                ],
                'occurred_at'  => $post->published_at,
            ]);

            \App\Modules\User\Controllers\CreatorPostController::notifyFollowersDebounced(
                $creator,
                'New post: ' . ($post->title ?: mb_substr($post->body, 0, 60)),
                $post
            );
            $count++;
        }
        return $count;
    }
}
