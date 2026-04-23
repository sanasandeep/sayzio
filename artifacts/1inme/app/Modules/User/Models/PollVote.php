<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class PollVote extends Model
{
    protected $table = 'poll_votes';

    protected $fillable = [
        'link_id', 'block_id', 'option_index', 'option_label',
        'user_id', 'voter_fingerprint', 'source',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'option_index' => 'integer',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function block()
    {
        return $this->belongsTo(BiolinkBlock::class, 'block_id');
    }
}
