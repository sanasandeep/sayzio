<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit row for GDPR-style poll-voter takedowns.
 * Created by PollVoteController::eraseVoter, never updated.
 */
class PollVoterErasure extends Model
{
    protected $table = 'poll_voter_erasures';

    public $timestamps = false;

    protected $fillable = [
        'creator_id', 'link_id', 'block_id',
        'identifier', 'removed_count', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'created_at'    => 'datetime',
        'removed_count' => 'integer',
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
