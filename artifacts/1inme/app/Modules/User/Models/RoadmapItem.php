<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class RoadmapItem extends Model
{
    use BelongsToWorkspace;

    public const STATUSES = [
        'pending'     => 'Pending review',
        'ideas'       => 'Ideas',
        'planned'     => 'Planned',
        'in_progress' => 'In Progress',
        'shipped'     => 'Shipped',
        'rejected'    => 'Rejected',
        'merged'      => 'Merged',
    ];

    public const PUBLIC_STATUSES = ['ideas', 'planned', 'in_progress', 'shipped'];

    protected $fillable = [
        'workspace_id', 'link_id', 'block_id', 'status', 'title', 'description',
        'votes_count', 'submitter_name', 'submitter_email', 'submitter_user_id',
        'submitter_fingerprint', 'submitter_ip', 'is_blocked', 'merged_into_id',
        'task_card_id', 'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            'is_blocked'   => 'boolean',
            'shipped_at'   => 'datetime',
            'votes_count'  => 'integer',
        ];
    }

    public function link()  { return $this->belongsTo(Link::class); }
    public function block() { return $this->belongsTo(BiolinkBlock::class, 'block_id'); }
    public function votes() { return $this->hasMany(RoadmapVote::class, 'item_id'); }
    public function comments() { return $this->hasMany(RoadmapComment::class, 'item_id'); }
    public function taskCard() { return $this->belongsTo(TaskCard::class, 'task_card_id'); }

    /** Used by BelongsToWorkspace when created from a public visitor flow. */
    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
