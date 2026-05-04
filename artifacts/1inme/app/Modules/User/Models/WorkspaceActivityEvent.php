<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceActivityEvent extends Model
{
    protected $table = 'workspace_activity_events';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'actor_user_id', 'action',
        'object_type', 'object_id', 'object_label', 'object_url',
        'payload', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
