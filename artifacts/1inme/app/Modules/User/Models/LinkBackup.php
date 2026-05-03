<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered backup destination for a link's "Link Insurance" config.
 * The lowest-position healthy backup is what the click handler serves
 * when the primary URL is failed-over.
 *
 * Workspace scoping is inherited transitively through link_id — the
 * parent Link already carries BelongsToWorkspace, so any query that
 * starts from $link->backups() is naturally scoped.
 */
class LinkBackup extends Model
{
    protected $fillable = [
        'link_id', 'position', 'url', 'label',
        'last_status', 'last_http_code', 'last_checked_at',
        'serve_count',
    ];

    protected function casts(): array
    {
        return [
            'position'        => 'integer',
            'last_http_code'  => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }
}
