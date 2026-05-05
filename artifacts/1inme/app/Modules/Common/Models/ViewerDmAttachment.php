<?php

namespace App\Modules\Common\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-message attachment row.
 *
 * `lock_price_cents` > 0 means the asset is paywalled — the renderer
 * shows the blur_url + a price/Unlock CTA until a row exists in
 * viewer_dm_attachment_unlocks for the viewing fan. Free attachments
 * (lock_price_cents = 0) render normally to anyone in the thread.
 */
class ViewerDmAttachment extends Model
{
    protected $table = 'viewer_dm_attachments';

    protected $fillable = [
        'message_id', 'conversation_id', 'owner_user_id',
        'kind', 'url', 'thumb_url', 'blur_url', 'mime',
        'size_bytes', 'duration_seconds',
        'lock_price_cents', 'lock_currency',
    ];

    protected $casts = [
        'lock_price_cents' => 'int',
        'size_bytes'       => 'int',
        'duration_seconds' => 'int',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ViewerDmMessage::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ViewerDmConversation::class, 'conversation_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(ViewerDmAttachmentUnlock::class, 'attachment_id');
    }

    public function isLocked(): bool
    {
        return (int) $this->lock_price_cents > 0;
    }

    /**
     * Has $fanId already paid for this attachment?
     * Owners always implicitly have access; checked at the policy layer.
     */
    public function isUnlockedFor(?int $fanId): bool
    {
        if (!$this->isLocked()) return true;
        if (!$fanId) return false;
        return $this->unlocks()
            ->where('fan_user_id', $fanId)
            ->whereNotNull('unlocked_at')
            ->whereNull('refunded_at')
            ->exists();
    }
}
