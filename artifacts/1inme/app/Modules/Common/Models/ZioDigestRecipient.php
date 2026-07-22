<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One per-recipient, per-channel send record for a Zio Digest broadcast.
 * status: queued | sent | failed | skipped
 */
class ZioDigestRecipient extends Model
{
    protected $fillable = ['digest_id', 'user_id', 'channel', 'status', 'error'];

    public function digest(): BelongsTo
    {
        return $this->belongsTo(ZioDigest::class, 'digest_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
