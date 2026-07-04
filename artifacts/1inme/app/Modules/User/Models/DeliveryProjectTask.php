<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Task #3564 — a single task on a {@see DeliveryProject}. Assignees are
 * workspace members (single assignee per task keeps the v1 scope light).
 */
class DeliveryProjectTask extends Model
{
    use BelongsToWorkspace;

    public const STATUS_TODO        = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';

    public const STATUSES = [
        self::STATUS_TODO        => 'To do',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_DONE        => 'Done',
    ];

    protected $fillable = [
        'project_id', 'workspace_id', 'title', 'status',
        'assignee_user_id', 'start_date', 'due_date', 'progress', 'position',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date'   => 'date',
            'progress'   => 'integer',
            'position'   => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DeliveryProject::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Keep progress and status coherent: moving to "done" implies 100%, and
     * setting progress to 100 implies "done" (and vice-versa for 0/todo).
     * Callers pass whichever field the user changed; this normalises the pair.
     */
    public function syncStatusProgress(?string $status = null, ?int $progress = null): void
    {
        if ($status !== null) {
            $this->status = $status;
            if ($status === self::STATUS_DONE) {
                $this->progress = 100;
            } elseif ($status === self::STATUS_TODO && $progress === null) {
                $this->progress = 0;
            }
        }

        if ($progress !== null) {
            $this->progress = max(0, min(100, $progress));
            if ($this->progress >= 100) {
                $this->status = self::STATUS_DONE;
            } elseif ($this->progress <= 0) {
                $this->status = $status ?? self::STATUS_TODO;
            } elseif ($this->status === self::STATUS_TODO) {
                $this->status = self::STATUS_IN_PROGRESS;
            }
        }
    }
}
