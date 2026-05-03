<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class BlockComment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'link_id', 'block_id', 'workspace_id', 'parent_id', 'post_id',
        'user_id', 'viewer_user_id', 'member_id',
        'author_name', 'author_email', 'body',
        'status', 'is_pinned', 'is_locked',
        'ip_address', 'user_agent',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public const STATUSES = ['visible', 'hidden', 'spam', 'deleted'];

    public function link()    { return $this->belongsTo(Link::class); }
    public function block()   { return $this->belongsTo(BiolinkBlock::class, 'block_id'); }
    public function parent()  { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies() { return $this->hasMany(self::class, 'parent_id')->where('status', 'visible')->oldest(); }
    public function member()  { return $this->belongsTo(CommunityMember::class, 'member_id'); }
    public function reactions() { return $this->hasMany(BlockReaction::class, 'comment_id'); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function scopeVisible($q) { return $q->where('status', 'visible'); }
}
