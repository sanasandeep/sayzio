<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxForwardDestination extends Model
{
    
    use BelongsToWorkspace;
protected $table = 'inbox_forward_destinations';

    protected $fillable = [
        'user_id', 'label', 'type', 'target', 'method', 'sources',
        'click_milestones',
        'header_key', 'header_value', 'secret', 'is_active',
        'last_delivered_at', 'last_status', 'last_failure_email_sent_at',
    ];

    protected $casts = [
        'sources'                    => 'array',
        'click_milestones'           => 'array',
        'is_active'                  => 'bool',
        'last_delivered_at'          => 'datetime',
        'last_failure_email_sent_at' => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(InboxForwardDelivery::class, 'destination_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Should this destination receive an event for the given source type? */
    public function matchesSource(string $sourceType): bool
    {
        $filter = $this->sources;
        if (empty($filter) || !is_array($filter)) return true;
        return in_array($sourceType, $filter, true);
    }

    /**
     * Returns configured click milestone thresholds as a sorted array of ints.
     * Empty array means no milestone tracking configured.
     *
     * @return int[]
     */
    public function clickMilestoneThresholds(): array
    {
        $raw = $this->click_milestones;
        if (empty($raw) || !is_array($raw)) return [];
        $vals = array_values(array_unique(array_filter(array_map('intval', $raw), fn ($v) => $v > 0)));
        sort($vals);
        return $vals;
    }
}
