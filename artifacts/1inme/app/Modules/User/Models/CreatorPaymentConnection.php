<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (creator, payout provider) hookup. See migration
 * 2027_05_07_010000_create_creator_payment_connections.php.
 *
 *  - `is_default` is mirrored from the registry-level "current default"
 *    so paid-feature routing can join on a single column without a
 *    secondary lookup.
 *  - `adult_friendly` is denormalised from PayoutProviderRegistry so
 *    the dashboard listing can filter without re-reading the registry
 *    on every page render.
 */
class CreatorPaymentConnection extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'account_id',
        'country',
        'status',
        'status_reason',
        'payouts_enabled',
        'charges_enabled',
        'is_default',
        'adult_friendly',
        'last_sync_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payouts_enabled' => 'boolean',
            'charges_enabled' => 'boolean',
            'is_default'      => 'boolean',
            'adult_friendly'  => 'boolean',
            'last_sync_at'    => 'datetime',
            'metadata'        => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Display label suitable for status badges + tooltips. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'active'      => 'Active',
            'pending'     => 'Pending verification',
            'restricted'  => 'Restricted',
            'disabled'    => 'Disabled',
            default       => ucfirst($this->status ?: 'Unknown'),
        };
    }

    /** Tailwind colour class fragment used by the UI badge. */
    public function statusColor(): string
    {
        return match ($this->status) {
            'active'     => 'emerald',
            'pending'    => 'amber',
            'restricted' => 'rose',
            'disabled'   => 'slate',
            default      => 'slate',
        };
    }
}
