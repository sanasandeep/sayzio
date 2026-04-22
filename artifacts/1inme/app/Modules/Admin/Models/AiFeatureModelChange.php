<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit-trail row for a single per-feature model switch on
 * /admin/ai-engine. Append-only: rows are never updated.
 */
class AiFeatureModelChange extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'feature',
        'old_model',
        'new_model',
        'admin_id',
        'admin_name',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
