<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks which contacts a user has opted to share with a specific workspace.
 * The contact owner controls sharing; workspace members (with view permission)
 * can see shared contacts; members with edit permission can edit them;
 * only the contact owner or workspace owner can unshare or delete.
 */
class ContactWorkspaceShare extends Model
{
    protected $fillable = ['contact_id', 'workspace_id', 'shared_by_user_id'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }
}
