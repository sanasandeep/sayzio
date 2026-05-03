<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationStep extends Model
{
    public const KIND_MESSAGE  = 'message';   // bot says something, auto-advance
    public const KIND_QUESTION = 'question';  // bot asks, visitor picks a choice
    public const KIND_INPUT    = 'input';     // free-text / email capture
    public const KIND_END      = 'end';       // terminal node, fires action

    public const KINDS = [
        self::KIND_MESSAGE  => 'Message',
        self::KIND_QUESTION => 'Quick-reply question',
        self::KIND_INPUT    => 'Input (text / email)',
        self::KIND_END      => 'End — trigger action',
    ];

    protected $fillable = [
        'flow_id', 'key', 'kind', 'message_text', 'answer_field',
        'sort_order', 'is_entry', 'skip_if_known', 'next_step_key',
        'action_id', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_entry'      => 'boolean',
            'skip_if_known' => 'boolean',
            'sort_order'    => 'integer',
            'settings'      => 'array',
        ];
    }

    public function flow(): BelongsTo    { return $this->belongsTo(ConversationFlow::class, 'flow_id'); }
    public function choices(): HasMany   { return $this->hasMany(ConversationChoice::class, 'step_id')->orderBy('sort_order'); }
    public function action(): BelongsTo  { return $this->belongsTo(ConversationAction::class, 'action_id'); }
}
