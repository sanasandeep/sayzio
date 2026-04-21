<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CloudFileAttachment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'cloud_file_id',
        'attachable_type', 'attachable_id',
        'attached_by_user_id',
    ];

    public function cloudFile()
    {
        return $this->belongsTo(CloudFile::class);
    }

    public function attachable()
    {
        return $this->morphTo();
    }
}
