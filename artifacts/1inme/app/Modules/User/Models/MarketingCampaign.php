<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    use BelongsToWorkspace;

    protected $guarded = ['id'];

    protected $casts = ['brief' => 'array', 'history' => 'array', 'strategy_ids' => 'array', 'studio_ids' => 'array'];
}
