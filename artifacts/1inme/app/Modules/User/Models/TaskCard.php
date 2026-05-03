<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskCard extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'board_id', 'column_id', 'created_by_user_id',
        'title', 'description', 'description_html', 'position',
        'due_date', 'priority', 'progress', 'completed_at', 'archived_at',
        'billable', 'rate_type', 'rate_amount_minor', 'client_invoice_id',
        'roadmap_item_id',
    ];

    public function roadmapItem() { return $this->belongsTo(RoadmapItem::class, 'roadmap_item_id'); }

    protected $casts = [
        'due_date'          => 'date',
        'completed_at'      => 'datetime',
        'archived_at'       => 'datetime',
        'billable'          => 'boolean',
        'rate_amount_minor' => 'integer',
    ];

    public function board()      { return $this->belongsTo(TaskBoard::class, 'board_id'); }
    public function column()     { return $this->belongsTo(TaskColumn::class, 'column_id'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function subtasks()   { return $this->hasMany(TaskSubtask::class, 'card_id')->orderBy('position'); }
    public function comments()   { return $this->hasMany(TaskComment::class, 'card_id')->orderBy('created_at'); }
    public function activities() { return $this->hasMany(TaskActivity::class, 'card_id')->orderByDesc('created_at'); }
    public function attachments(){ return $this->hasMany(TaskAttachment::class, 'card_id')->orderByDesc('id'); }
    public function timeEntries(){ return $this->hasMany(TaskTimeEntry::class, 'card_id')->orderBy('started_at'); }
    public function clientInvoice(){ return $this->belongsTo(Invoice::class, 'client_invoice_id'); }

    /** Sum of un-invoiced minutes (for hourly billing previews). */
    public function unbilledMinutes(): int
    {
        return (int) $this->timeEntries()
            ->whereNull('client_invoice_id')
            ->whereNotNull('ended_at')
            ->sum('minutes');
    }

    /** Is there a running timer on this card right now? */
    public function runningTimer(): ?TaskTimeEntry
    {
        return $this->timeEntries()
            ->where('source', 'timer')
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->first();
    }

    public function cloudAttachments()
    {
        return $this->morphMany(CloudFileAttachment::class, 'attachable');
    }

    protected static function booted(): void
    {
        static::deleting(function (TaskCard $card) {
            $card->cloudAttachments()->delete();
        });
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_card_assignees', 'card_id', 'user_id')
            ->withTimestamps();
    }

    public function labels()
    {
        return $this->belongsToMany(TaskLabel::class, 'task_card_labels', 'card_id', 'label_id')
            ->withTimestamps();
    }

    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    public static function priorities(): array
    {
        return [
            'low'    => ['label' => 'Low',    'color' => '#94a3b8'],
            'normal' => ['label' => 'Normal', 'color' => '#3b82f6'],
            'high'   => ['label' => 'High',   'color' => '#f59e0b'],
            'urgent' => ['label' => 'Urgent', 'color' => '#ef4444'],
        ];
    }
}
