<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI Brand Studio run (Task #5551): the brief + brand context the user
 * submitted, the structured multi-asset proposal the AI produced, and — once
 * confirmed — references to every asset that was created as part of the kit.
 *
 * Owned by `user_id` (no workspace scope) like BrandKit, so the Sanctum API
 * path and the web path resolve rows identically.
 */
class BrandStudioKit extends Model
{
    public const MODE_KIT  = 'kit';
    public const MODE_BULK = 'bulk';

    public const STATUS_PROPOSAL = 'proposal';
    public const STATUS_CREATED  = 'created';

    protected $fillable = [
        'user_id', 'name', 'mode', 'status',
        'request', 'brand', 'proposal', 'results', 'credits_spent',
    ];

    protected $casts = [
        'brand'    => 'array',
        'proposal' => 'array',
        'results'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCreated(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    /** @return list<array<string,mixed>> */
    public function proposedAssets(): array
    {
        $assets = $this->proposal['assets'] ?? [];
        return is_array($assets) ? array_values($assets) : [];
    }

    /** @return list<array<string,mixed>> */
    public function createdAssets(): array
    {
        $assets = $this->results['assets'] ?? [];
        return is_array($assets) ? array_values($assets) : [];
    }
}
