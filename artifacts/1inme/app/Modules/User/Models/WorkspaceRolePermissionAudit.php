<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceRolePermissionAudit extends Model
{
    protected $table = 'workspace_role_permission_audits';

    public $timestamps = false;

    protected $fillable = ['workspace_id', 'user_id', 'changes', 'created_at'];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
