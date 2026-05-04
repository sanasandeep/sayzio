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
    ];

    protected $casts = [
        'scheduled_at'          => 'datetime',
        'published_at'          => 'datetime',
        'pinned_at'             => 'datetime',
        'approval_requested_at' => 'datetime',
        'approval_decided_at'   => 'datetime',
        'intended_scheduled_at' => 'datetime',
    ];

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
                    'creator_avatar' => $creator->avatar,
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
