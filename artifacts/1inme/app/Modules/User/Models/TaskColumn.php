<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskColumn extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'board_id', 'name', 'color',
        'position', 'wip_limit', 'is_done',
    ];

    protected $casts = [
        'is_done'   => 'boolean',
        'wip_limit' => 'integer',
    ];

    public function board()
    {
        return $this->belongsTo(TaskBoard::class, 'board_id');
    }

    public function cards()
    {
        return $this->hasMany(TaskCard::class, 'column_id')
            ->whereNull('archived_at')
            ->orderBy('position');
    }
}
