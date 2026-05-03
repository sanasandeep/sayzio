<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapVote extends Model
{
    protected $fillable = [
        'item_id', 'viewer_user_id', 'fingerprint', 'email', 'ip',
    ];

    public function item() { return $this->belongsTo(RoadmapItem::class, 'item_id'); }
}
