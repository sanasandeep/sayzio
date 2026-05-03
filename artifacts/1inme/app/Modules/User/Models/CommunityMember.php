<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CommunityMember extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id', 'link_id', 'block_id', 'workspace_id',
        'subscriber_id', 'viewer_user_id',
        'email', 'display_name', 'tier', 'status',
        'stripe_subscription_id', 'joined_at', 'expires_at', 'preferences',
    ];

    protected $casts = [
        'joined_at'   => 'datetime',
        'expires_at'  => 'datetime',
        'preferences' => 'array',
    ];

    public const TIERS    = ['free', 'paid'];
    public const STATUSES = ['active', 'cancelled', 'banned'];

    public function user()       { return $this->belongsTo(User::class); }
    public function link()       { return $this->belongsTo(Link::class); }
    public function block()      { return $this->belongsTo(BiolinkBlock::class, 'block_id'); }
    public function subscriber() { return $this->belongsTo(Subscriber::class); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active')
            ->where(function ($qq) {
                $qq->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (is_null($this->expires_at) || $this->expires_at->isFuture());
    }
}
