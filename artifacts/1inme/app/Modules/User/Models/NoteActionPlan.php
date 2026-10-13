<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class NoteActionPlan extends Model
{
    use BelongsToWorkspace;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result' => 'array', 'actions' => 'array', 'planner_settings' => 'array', 'shared' => 'boolean', 'automation_enabled' => 'boolean', 'next_run_at' => 'datetime'];
    }
}
