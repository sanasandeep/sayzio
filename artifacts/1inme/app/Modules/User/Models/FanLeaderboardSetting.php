<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class FanLeaderboardSetting extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id', 'link_id', 'workspace_id',
        'is_enabled', 'show_anonymous_option',
        'point_rules', 'perks', 'top_n',
    ];

    protected $casts = [
        'is_enabled'            => 'boolean',
        'show_anonymous_option' => 'boolean',
        'point_rules'           => 'array',
        'perks'                 => 'array',
        'top_n'                 => 'integer',
    ];

    public static function defaultRules(): array
    {
        return ['share' => 5, 'click' => 1, 'comment' => 3, 'reaction' => 1, 'referral' => 25, 'signup' => 10, 'post' => 0];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function link() { return $this->belongsTo(Link::class); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }
}
