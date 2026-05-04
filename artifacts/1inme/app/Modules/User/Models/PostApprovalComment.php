<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class PostApprovalComment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'creator_post_id', 'user_id', 'action', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(CreatorPost::class, 'creator_post_id');
    }

    /** Friendly label for the action chip displayed next to the comment. */
    public function actionLabel(): ?string
    {
        return [
            'submit'             => 'Submitted for review',
            'approve'            => 'Approved',
            'changes_requested'  => 'Requested changes',
            'reject'             => 'Rejected',
        ][$this->action] ?? null;
    }
}
