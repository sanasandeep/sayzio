<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ProjectItem extends Model
{
    use BelongsToWorkspace;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [];
    }
}
