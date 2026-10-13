<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class PlannerNote extends Model
{
    use BelongsToWorkspace;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['shared' => 'boolean', 'pinned' => 'boolean', 'snoozed_until' => 'datetime', 'completed_at' => 'datetime'];
    }
}
