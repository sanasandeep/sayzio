<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot of a poll's per-option vote counts captured immediately
 * before the creator clicks "Reset votes". Lets the creator see what
 * was cleared and (optionally) recreate anonymized vote rows.
 */
class PollVoteResetSnapshot extends Model
{
    protected $table = 'poll_vote_reset_snapshots';

    public $timestamps = false;

    protected $fillable = [
        'creator_id', 'link_id', 'block_id',
        'counts', 'total', 'reset_at', 'restored_at', 'ip_address',
    ];

    protected $casts = [
        'counts'      => 'array',
        'total'       => 'integer',
        'reset_at'    => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function block()
    {
        return $this->belongsTo(BiolinkBlock::class, 'block_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
