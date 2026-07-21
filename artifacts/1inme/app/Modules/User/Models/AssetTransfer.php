<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit record of one completed asset transfer (a link or a
 * whole workspace) between two user accounts. Written inside the same
 * transaction as the transfer itself by AssetTransferService.
 */
class AssetTransfer extends Model
{
    public const KIND_LINK      = 'link';
    public const KIND_WORKSPACE = 'workspace';

    protected $fillable = [
        'kind', 'asset_id', 'asset_label',
        'from_user_id', 'to_user_id', 'from_email', 'to_email',
        'channel', 'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
