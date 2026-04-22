<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\AiCompanion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One visitor's chat session with an AI Companion. Identified by an
 * opaque `visitor_token` (cookie / localStorage on the visitor side)
 * so anonymous returning users can resume.
 */
class AiCompanionConversation extends Model
{
    protected $table = 'ai_companion_conversations';

    protected $fillable = [
        'companion_id', 'visitor_token', 'visitor_name', 'visitor_email',
        'visitor_ip', 'visitor_ua', 'source_origin',
        'rating', 'turns_count', 'credits_spent', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'rating'          => 'integer',
            'turns_count'     => 'integer',
            'credits_spent'   => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function companion(): BelongsTo
    {
        return $this->belongsTo(AiCompanion::class, 'companion_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiCompanionMessage::class, 'conversation_id')->orderBy('id');
    }
}
