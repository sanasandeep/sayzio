<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * State row for the Leads review queue (Task #3728). A lead is "pending"
 * when NO row exists here for a given (source_type, source_id) pair —
 * see {@see \App\Modules\User\Services\LeadAggregator}. A row is only
 * written when the owner acts on an item (approve or dismiss).
 */
class Lead extends Model
{
    use BelongsToWorkspace;

    public const STATUS_APPROVED  = 'approved';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'workspace_id', 'user_id', 'source_type', 'source_id', 'status',
        'contact_id', 'actor_user_id', 'approved_at', 'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at'  => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
