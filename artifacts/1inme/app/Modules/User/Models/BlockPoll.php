<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class BlockPoll extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'link_id', 'block_id', 'post_id', 'workspace_id',
        'question', 'options', 'visibility',
        'multi_select', 'is_closed', 'closes_at',
    ];

    protected $casts = [
        'options'      => 'array',
        'multi_select' => 'boolean',
        'is_closed'    => 'boolean',
        'closes_at'    => 'datetime',
    ];

    public const VISIBILITIES = ['public', 'members', 'followers'];

    public function block() { return $this->belongsTo(BiolinkBlock::class, 'block_id'); }
    public function post()  { return $this->belongsTo(CommunityPost::class, 'post_id'); }
    public function votes() { return $this->hasMany(BlockPollVote::class, 'poll_id'); }

    public function parentForWorkspace()
    {
        if ($this->link_id) {
            return Link::withoutGlobalScope('workspace')->find($this->link_id);
        }
        return null;
    }

    public function isOpen(): bool
    {
        if ($this->is_closed) return false;
        if ($this->closes_at && $this->closes_at->isPast()) return false;
        return true;
    }

    public function tally(): array
    {
        $rows = $this->votes()
            ->selectRaw('option_index, COUNT(*) as c')
            ->groupBy('option_index')
            ->pluck('c', 'option_index')
            ->all();
        $opts = $this->options ?? [];
        $total = array_sum($rows);
        $out = [];
        foreach ($opts as $i => $label) {
            $count = (int)($rows[$i] ?? 0);
            $out[] = [
                'index' => $i,
                'label' => is_array($label) ? ($label['label'] ?? '') : $label,
                'count' => $count,
                'pct'   => $total > 0 ? round($count * 100 / $total) : 0,
            ];
        }
        return ['total' => $total, 'options' => $out];
    }
}
