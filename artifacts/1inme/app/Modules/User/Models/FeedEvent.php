<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class FeedEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'type', 'subject_id', 'subject_type', 'data', 'occurred_at', 'visibility', 'is_demo'];
    protected $casts = ['data' => 'array', 'occurred_at' => 'datetime', 'is_demo' => 'boolean'];

    public const VISIBILITY_LEVELS = ['public', 'registered', 'followers', 'subscribers'];

    public static function visibilityLabel(?string $v): string
    {
        return [
            'public'      => 'Public',
            'registered'  => 'Members only',
            'followers'   => 'Followers only',
            'subscribers' => 'Subscribers only',
        ][$v ?? 'public'] ?? 'Public';
    }

    public function user() { return $this->belongsTo(User::class); }
}
