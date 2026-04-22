<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single turn in an AI Companion conversation. `role` follows the
 * OpenAI chat convention so messages can be replayed straight into
 * PersonaRuntime without a translation step.
 */
class AiCompanionMessage extends Model
{
    protected $table = 'ai_companion_messages';

    protected $fillable = [
        'conversation_id', 'role', 'content', 'citations',
        'credits_spent', 'rating', 'is_flagged',
    ];

    protected function casts(): array
    {
        return [
            'citations'     => 'array',
            'credits_spent' => 'integer',
            'rating'        => 'integer',
            'is_flagged'    => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiCompanionConversation::class, 'conversation_id');
    }
}
