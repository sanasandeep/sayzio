<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Rsvp extends Model
{
    protected $table = 'rsvps';

    protected $fillable = [
        'link_id', 'name', 'email', 'phone', 'response', 'plus_ones',
        'message', 'source', 'source_block_id', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'plus_ones' => 'integer',
        ];
    }

    public const RESPONSES = ['yes' => 'Going', 'maybe' => 'Maybe', 'no' => 'Not going'];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
