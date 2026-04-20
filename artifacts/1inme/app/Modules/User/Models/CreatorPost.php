<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorPost extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'image', 'scheduled_at', 'published_at', 'pinned_at'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'pinned_at'    => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }

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

    public function statusLabel(): string
    {
        if ($this->isScheduled()) return 'Scheduled';
        if ($this->isPinned()) return 'Pinned';
        return 'Published';
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
                'New post: ' . ($post->title ?: mb_substr($post->body, 0, 60))
            );
            $count++;
        }
        return $count;
    }
}
