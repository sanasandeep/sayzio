<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboxForwardDestination extends Model
{
    protected $table = 'inbox_forward_destinations';

    protected $fillable = [
        'user_id', 'label', 'type', 'target', 'method', 'sources',
        'header_key', 'header_value', 'secret', 'is_active',
        'last_delivered_at', 'last_status',
    ];

    protected $casts = [
        'sources'            => 'array',
        'is_active'          => 'bool',
        'last_delivered_at'  => 'datetime',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(InboxForwardDelivery::class, 'destination_id');
    }

    /** Should this destination receive an event for the given source type? */
    public function matchesSource(string $sourceType): bool
    {
        $filter = $this->sources;
        if (empty($filter) || !is_array($filter)) return true;
        return in_array($sourceType, $filter, true);
    }
}
