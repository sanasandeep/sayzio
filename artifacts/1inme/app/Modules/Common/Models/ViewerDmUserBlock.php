<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViewerDmUserBlock extends Model
{
    protected $table = 'viewer_dm_user_blocks';

    protected $fillable = ['owner_user_id', 'viewer_user_id', 'reason'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }
}
