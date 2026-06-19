<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only ledger row for a single one-click schema column repair run
 * (the dashboard "Fix now" button →
 * {@see \App\Modules\Common\Support\ExpectedSchemaHealth::repair()}).
 *
 * Captures WHO ran the repair, WHEN, and the schema-level outcome — the
 * columns added/backfilled per table and any whole-missing tables it could
 * not recreate. Only schema metadata is stored, never row data. Written by
 * {@see \App\Modules\Admin\Controllers\DashboardController::repairExpectedColumns()}
 * and surfaced read-only at the admin repair-audit page so reviewers can see
 * who touched the live schema and what changed.
 */
class SchemaRepairAudit extends Model
{
    protected $table = 'schema_repair_audits';

    public $timestamps = false;

    protected $fillable = [
        'actor_admin_id', 'actor_user_id', 'actor_guard',
        'actor_name', 'actor_email',
        'added', 'unrepairable',
        'added_columns_count', 'added_tables_count', 'unrepairable_count',
        'ip', 'created_at',
    ];

    protected $casts = [
        'added'                => 'array',
        'unrepairable'         => 'array',
        'added_columns_count'  => 'integer',
        'added_tables_count'   => 'integer',
        'unrepairable_count'   => 'integer',
        'created_at'           => 'datetime',
    ];

    public function actorAdmin()
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Best-effort label for the actor — prefers the live record's current
     * name/email, falls back to the snapshot, and finally to "System" when
     * neither side is populated (e.g. a CLI invocation).
     */
    public function actorLabel(): string
    {
        if ($this->actor_guard === 'admin' && $this->actorAdmin) {
            return ($this->actorAdmin->name ?: $this->actorAdmin->email ?: ('Admin #' . $this->actor_admin_id))
                . ' (admin)';
        }
        if ($this->actor_guard === 'web' && $this->actorUser) {
            return $this->actorUser->name ?: ($this->actorUser->email ?: ('User #' . $this->actor_user_id));
        }
        if ($this->actor_name) {
            return $this->actor_guard === 'admin'
                ? $this->actor_name . ' (admin)'
                : $this->actor_name;
        }
        return 'System';
    }

    /**
     * Whether this run actually changed the schema (added at least one
     * column). A no-op run (everything already present) is still recorded
     * for the audit trail.
     */
    public function changedSchema(): bool
    {
        return $this->added_columns_count > 0;
    }
}
