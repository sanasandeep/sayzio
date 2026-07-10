<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single committed pricing-plans CSV import, together with the full
 * before-state of every plan captured at commit time. This is the undo
 * payload for {@see \App\Modules\Admin\Controllers\PlanController::revertImport()}:
 * reverting an import restores every plan to exactly the snapshot stored here.
 */
class PlanImportSnapshot extends Model
{
    protected $fillable = [
        'admin_id', 'admin_name',
        'plans_updated', 'rows_skipped',
        'changed', 'snapshot',
        'reverted_at', 'reverted_by', 'reverted_by_name',
    ];

    protected function casts(): array
    {
        return [
            'changed' => 'array',
            'snapshot' => 'array',
            'reverted_at' => 'datetime',
            'plans_updated' => 'integer',
            'rows_skipped' => 'integer',
        ];
    }

    /**
     * True once this import has been reverted (can only be undone once).
     */
    public function isReverted(): bool
    {
        return $this->reverted_at !== null;
    }
}
