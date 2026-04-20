<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-conflict acknowledgement record for a banned-name entry. When an
 * admin marks a specific user/link/extra-alias conflict as "handled",
 * we insert a row here so the drill-in view can hide it without
 * touching the actual user/link record. Composite uniqueness on
 * (banned_name_id, conflict_type, conflict_id) is enforced by the
 * migration.
 */
class BannedNameAcknowledgement extends Model
{
    protected $fillable = [
        'banned_name_id', 'conflict_type', 'conflict_id',
        'acknowledged_by', 'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function bannedName()
    {
        return $this->belongsTo(BannedName::class);
    }
}
