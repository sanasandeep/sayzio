<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BlockPollVote extends Model
{
    protected $fillable = ['poll_id', 'option_index', 'viewer_user_id', 'voter_fingerprint'];

    public function poll() { return $this->belongsTo(BlockPoll::class, 'poll_id'); }
}
