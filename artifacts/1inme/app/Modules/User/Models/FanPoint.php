<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class FanPoint extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id', 'link_id', 'workspace_id',
        'viewer_user_id', 'voter_fingerprint', 'display_name',
        'action', 'points', 'subject_id', 'subject_type', 'meta',
    ];

    protected $casts = ['meta' => 'array', 'points' => 'integer'];

    public const ACTIONS = ['share', 'click', 'comment', 'reaction', 'referral', 'signup', 'post'];

    public function user() { return $this->belongsTo(User::class); }
    public function link() { return $this->belongsTo(Link::class); }

    public function subject() { return $this->morphTo(); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }
}
