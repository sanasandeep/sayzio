<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AiGrowthReport extends Model
{
    protected $guarded = [];

    protected $casts = ['context' => 'array', 'report' => 'array'];
}
