<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-(workspace, action) toggle for whether the workspace owner is
 * emailed when a sensitive action fires. Falls back to the action's
 * default in SensitiveActionLogger::CATALOG when no row exists.
 */
class WorkspaceAuditAlertPref extends Model
{
    protected $table = 'workspace_audit_alert_prefs';

    protected $fillable = ['workspace_id', 'action', 'alert_enabled'];

    protected $casts = ['alert_enabled' => 'boolean'];
}
