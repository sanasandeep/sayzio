<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class BlockReaction extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'link_id', 'block_id', 'comment_id', 'post_id', 'workspace_id',
        'viewer_user_id', 'voter_fingerprint', 'emoji',
    ];

    public const EMOJIS = ['👍', '❤️', '😂', '🔥', '🎉', '👏', '😮', '😢'];

    public function block()   { return $this->belongsTo(BiolinkBlock::class, 'block_id'); }
    public function comment() { return $this->belongsTo(BlockComment::class, 'comment_id'); }
    public function post()    { return $this->belongsTo(CommunityPost::class, 'post_id'); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }
}
