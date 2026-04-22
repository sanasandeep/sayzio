<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceRolePermission extends Model
{
    protected $table = 'workspace_role_permissions';

    protected $fillable = ['workspace_id', 'matrix'];

    protected $casts = ['matrix' => 'array'];
}
