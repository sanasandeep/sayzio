<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskCard extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'board_id', 'column_id', 'created_by_user_id',
        'title', 'description', 'position', 'due_date', 'priority',
        'completed_at', 'archived_at',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'archived_at'  => 'datetime',
    ];

    public function board()      { return $this->belongsTo(TaskBoard::class, 'board_id'); }
    public function column()     { return $this->belongsTo(TaskColumn::class, 'column_id'); }
    public function creator()    { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function subtasks()   { return $this->hasMany(TaskSubtask::class, 'card_id')->orderBy('position'); }
    public function comments()   { return $this->hasMany(TaskComment::class, 'card_id')->orderBy('created_at'); }
    public function activities() { return $this->hasMany(TaskActivity::class, 'card_id')->orderByDesc('created_at'); }

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
