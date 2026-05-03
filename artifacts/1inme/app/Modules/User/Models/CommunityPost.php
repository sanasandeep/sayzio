<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CommunityPost extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id', 'link_id', 'block_id', 'workspace_id',
        'title', 'body', 'media_type', 'media_url',
        'access', 'status', 'scheduled_for', 'published_at',
        'reactions_count', 'comments_count', 'meta',
    ];

    protected $casts = [
        'scheduled_for'   => 'datetime',
        'published_at'    => 'datetime',
        'reactions_count' => 'integer',
        'comments_count'  => 'integer',
        'meta'            => 'array',
    ];

    public const ACCESS_LEVELS = ['public', 'members', 'paid', 'followers'];
    public const STATUSES      = ['draft', 'scheduled', 'published', 'archived'];

    public function user()  { return $this->belongsTo(User::class); }
    public function link()  { return $this->belongsTo(Link::class); }
    public function block() { return $this->belongsTo(BiolinkBlock::class, 'block_id'); }

    public function comments()
    {
        // Comments are stored on BlockComment with both block_id and
        // (optional) post_id. The default relation must be scoped to
        // *this* post so we don't accidentally bleed in comments from
        // sibling posts on the same block.
        return $this->hasMany(BlockComment::class, 'post_id', 'id')
            ->where('status', 'visible')
            ->whereNull('parent_id')
            ->latest();
    }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeForLink($query, int $linkId)
    {
        return $query->where('link_id', $linkId);
    }
}
