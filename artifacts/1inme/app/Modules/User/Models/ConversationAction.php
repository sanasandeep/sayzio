<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationAction extends Model
{
    public const KIND_OPEN_LINK     = 'open_link';
    public const KIND_SHOW_BLOCK    = 'show_block';
    public const KIND_CAPTURE_EMAIL = 'capture_email';
    public const KIND_BOOK_CALENDAR = 'book_calendar';
    public const KIND_MESSAGE       = 'message';

    public const KINDS = [
        self::KIND_OPEN_LINK     => 'Open a URL',
        self::KIND_SHOW_BLOCK    => 'Reveal a block on this page',
        self::KIND_CAPTURE_EMAIL => 'Capture email (subscribe)',
        self::KIND_BOOK_CALENDAR => 'Send to calendar booking',
        self::KIND_MESSAGE       => 'Show a final message',
    ];

    protected $fillable = ['flow_id', 'kind', 'label', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function flow(): BelongsTo { return $this->belongsTo(ConversationFlow::class, 'flow_id'); }
}
