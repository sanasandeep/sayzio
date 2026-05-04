<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationStep extends Model
{
    public const KIND_MESSAGE     = 'message';     // bot says something, auto-advance
    public const KIND_QUESTION    = 'question';    // bot asks, visitor picks a choice (single or multi)
    public const KIND_INPUT       = 'input';       // free-text / email / phone / url / number capture
    public const KIND_END         = 'end';         // terminal node, fires action
    public const KIND_MEDIA       = 'media';       // image / gif / video / audio bubble
    public const KIND_FILE_UPLOAD = 'file_upload'; // visitor attaches a file
    public const KIND_RATING      = 'rating';      // star (1-5) / NPS (0-10) / emoji
    public const KIND_DATETIME    = 'datetime';    // date / time / datetime picker
    public const KIND_AI_FREETEXT = 'ai_freetext'; // free text classified into intents

    public const KINDS = [
        self::KIND_MESSAGE     => 'Message',
        self::KIND_QUESTION    => 'Quick-reply question',
        self::KIND_INPUT       => 'Input (text / email / phone / url / number)',
        self::KIND_MEDIA       => 'Media (image / video / audio)',
        self::KIND_FILE_UPLOAD => 'File upload',
        self::KIND_RATING      => 'Rating (stars / NPS / emoji)',
        self::KIND_DATETIME    => 'Date / time picker',
        self::KIND_AI_FREETEXT => 'AI free-text (auto-route)',
        self::KIND_END         => 'End — trigger action',
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
