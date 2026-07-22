<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI-generated visual asset attached to a Brand Kit (Task #5612).
 *
 * A kit holds at most one asset per `type` (logo, favicon, letterhead,
 * social banners, avatar, OG image, business card, email banner,
 * background, watermark); regenerating replaces the stored image and
 * increments `version`. The image itself is a {@see UserFile} (S3-backed,
 * quota-counted); `credits_spent` records the coin charge for the CURRENT
 * version so refunds/audits can be traced in the wallet ledger.
 */
class BrandKitAsset extends Model
{
    public const STATUS_READY  = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'brand_kit_id',
        'user_id',
        'type',
        'status',
        'user_file_id',
        'prompt',
        'params',
        'version',
        'credits_spent',
    ];

    protected $casts = [
        'params' => 'array',
    ];

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(BrandKit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(UserFile::class, 'user_file_id');
    }
}
