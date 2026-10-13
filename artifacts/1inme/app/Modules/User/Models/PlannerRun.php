<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class PlannerRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
