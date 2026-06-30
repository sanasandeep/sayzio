<?php

namespace App\Modules\User\Models;

use App\Modules\Admin\Models\AccountBadge;
use Illuminate\Database\Eloquent\Model;

/**
 * A self-serve request from a user to be granted an account badge
 * (Task #2910). Either references an existing {@see AccountBadge}
 * (`account_badge_id`) or carries a free-text `custom_name` the admin
 * maps to / creates a badge for on approval.
 */
class BadgeRequest extends Model
{
    protected $fillable = [
        'user_id', 'account_badge_id', 'custom_name', 'reason',
        'status', 'assigned_badge_id', 'reviewed_by', 'admin_notes', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The existing badge that was requested (custom requests have none). */
    public function badge()
    {
        return $this->belongsTo(AccountBadge::class, 'account_badge_id');
    }

    /** The badge actually attached when the request was approved. */
    public function assignedBadge()
    {
        return $this->belongsTo(AccountBadge::class, 'assigned_badge_id');
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    /** Human-readable label for what the user asked for. */
    public function requestedLabel(): string
    {
        if ($this->account_badge_id && $this->badge) {
            return $this->badge->name;
        }

        return $this->custom_name !== null && $this->custom_name !== ''
            ? $this->custom_name
            : 'Custom badge';
    }
}
