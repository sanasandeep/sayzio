<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Threaded comments on a CreatorPost (one reply level — enforced in
 * the controller). Authors are always registered viewers; anonymous
 * comments are not allowed on the new /@handle profile surface.
 */
class CreatorPostComment extends Model
{
    protected $fillable = [
        'post_id', 'parent_id', 'viewer_user_id', 'body', 'status',
    ];

    public const STATUSES = ['visible', 'hidden', 'deleted'];

    public function post()    { return $this->belongsTo(CreatorPost::class, 'post_id'); }
    public function parent()  { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies() { return $this->hasMany(self::class, 'parent_id')->where('status', 'visible')->oldest(); }
    public function viewer()  { return $this->belongsTo(User::class, 'viewer_user_id'); }

    public function scopeVisible($q) { return $q->where('status', 'visible'); }
}
