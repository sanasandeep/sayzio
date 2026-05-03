<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit row for a single probe of a link destination
 * (primary or backup). Powers the per-link uptime sparkline and the
 * incident timeline on the Link Health dashboard.
 *
 * Workspace scoping is inherited transitively through link_id — the
 * parent Link carries BelongsToWorkspace, so any query starting from
 * $link->healthChecks() is naturally scoped.
 */
class LinkHealthCheck extends Model
{
    protected $fillable = [
        'link_id', 'link_backup_id', 'target_url', 'status',
        'http_code', 'latency_ms', 'error_class', 'error_detail',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'http_code'  => 'integer',
            'latency_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(LinkBackup::class, 'link_backup_id');
    }
}
