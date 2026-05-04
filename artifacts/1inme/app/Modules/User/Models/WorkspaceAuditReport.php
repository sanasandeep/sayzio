<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "This wasn't me" reports filed against a sensitive workspace action.
 * One audit event can have multiple reports (e.g. multiple owners flag
 * the same suspicious export). Append-only.
 */
class WorkspaceAuditReport extends Model
{
    protected $table = 'workspace_audit_reports';

    public $timestamps = false;

    protected $fillable = [
        'workspace_audit_event_id', 'reporter_user_id', 'reporter_email',
        'ip', 'note', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function event()
    {
        return $this->belongsTo(WorkspaceAuditEvent::class, 'workspace_audit_event_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }
}
