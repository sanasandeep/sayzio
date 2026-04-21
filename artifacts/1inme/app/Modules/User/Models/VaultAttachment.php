<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class VaultAttachment extends Model
{
    use BelongsToWorkspace;

    protected $table = 'vault_attachments';
    protected $fillable = [
        'workspace_id', 'uploaded_by_user_id',
        'parent_type', 'parent_id',
        'filename', 'disk', 'path', 'size', 'mime', 'encrypted',
    ];

    protected $casts = [
        'encrypted' => 'boolean',
    ];
}
