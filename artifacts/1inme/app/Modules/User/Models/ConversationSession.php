<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ConversationSession extends Model
{
    protected $fillable = [
        'flow_id', 'link_id', 'public_id', 'flow_version', 'flow_snapshot',
        'page_session_id', 'contact_id',
        'subscriber_id', 'current_step_key', 'answers', 'path',
        'completed', 'completed_action_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'flow_snapshot' => 'array',
            'answers'       => 'array',
            'path'          => 'array',
            'completed'     => 'boolean',
            'completed_at'  => 'datetime',
        ];
    }

    public function flow(): BelongsTo    { return $this->belongsTo(ConversationFlow::class, 'flow_id'); }
    public function link(): BelongsTo    { return $this->belongsTo(Link::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            if (empty($s->public_id)) {
                $s->public_id = 'cvs_' . Str::lower(Str::random(20));
            }
        });
    }
}
