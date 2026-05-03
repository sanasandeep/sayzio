<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationChoice extends Model
{
    protected $fillable = [
        'step_id', 'label', 'value', 'next_step_key', 'action_id', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function step(): BelongsTo   { return $this->belongsTo(ConversationStep::class, 'step_id'); }
    public function action(): BelongsTo { return $this->belongsTo(ConversationAction::class, 'action_id'); }
}
