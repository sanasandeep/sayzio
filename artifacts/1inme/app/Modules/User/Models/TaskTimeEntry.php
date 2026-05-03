<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * A single time log against a TaskCard. `minutes` is denormalised from
 * (started_at, ended_at) for `timer` rows so reporting can sum without
 * a date diff per row, and is the source of truth for `manual` entries.
 *
 * `client_invoice_id` pins the entry to a specific client invoice once
 * the card has been billed, so re-invoicing the same card later doesn't
 * double-bill already-invoiced minutes.
 */
class TaskTimeEntry extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'card_id', 'user_id',
        'started_at', 'ended_at', 'minutes', 'note', 'source',
        'client_invoice_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'minutes'    => 'integer',
    ];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }

    public function isRunning(): bool
    {
        return $this->source === 'timer' && $this->started_at && !$this->ended_at;
    }

    /**
     * Workspace-scope inheritance for entries created without a current
     * workspace context (e.g. webhook back-fills).
     */
    public function parentForWorkspace()
    {
        return $this->card_id ? TaskCard::query()->withoutWorkspaceScope()->find($this->card_id) : null;
    }
}
