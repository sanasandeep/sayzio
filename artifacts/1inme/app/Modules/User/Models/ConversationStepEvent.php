<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationStepEvent extends Model
{
    public $timestamps = false;

    public const EVENT_ENTERED          = 'entered';
    public const EVENT_ANSWERED         = 'answered';
    public const EVENT_DROPPED          = 'dropped';
    public const EVENT_COMPLETED        = 'completed';
    public const EVENT_VALIDATION_FAIL  = 'validation_failed';
    public const EVENT_AI_CLASSIFIED    = 'ai_classified';

    protected $fillable = [
        'session_id', 'flow_id', 'step_key', 'event', 'choice_value', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function session(): BelongsTo { return $this->belongsTo(ConversationSession::class, 'session_id'); }
    public function flow(): BelongsTo    { return $this->belongsTo(ConversationFlow::class, 'flow_id'); }
}
