<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rolling conversation memory for the two-way WhatsApp AI agent
 * (Task #2759). One row per (user, WhatsApp phone). `history` is the
 * trimmed [{role, content}] window the model sees on each turn;
 * `pending` is media the user sent ahead of the instruction that
 * consumes it (images/files as URLs, voice notes as transcripts).
 */
class WhatsAppAgentConversation extends Model
{
    protected $table = 'whatsapp_agent_conversations';

    /** Keep the model history short so token spend per turn stays bounded. */
    public const MAX_HISTORY = 16;

    protected $fillable = [
        'user_id', 'wa_phone', 'history', 'pending', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'history'         => 'array',
            'pending'         => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Append a turn and trim the window to the most recent MAX_HISTORY entries. */
    public function pushHistory(string $role, string $content): void
    {
        $history = is_array($this->history) ? $this->history : [];
        $history[] = ['role' => $role, 'content' => $content];
        if (count($history) > self::MAX_HISTORY) {
            $history = array_slice($history, -self::MAX_HISTORY);
        }
        $this->history = array_values($history);
    }

    /** Queue a media item the next instruction may reference. */
    public function pushPending(array $item): void
    {
        $pending = is_array($this->pending) ? $this->pending : [];
        $pending[] = $item;
        $this->pending = array_values($pending);
    }

    /** Drain (read + clear) the pending media bucket. */
    public function takePending(): array
    {
        $pending = is_array($this->pending) ? $this->pending : [];
        $this->pending = [];
        return $pending;
    }
}
