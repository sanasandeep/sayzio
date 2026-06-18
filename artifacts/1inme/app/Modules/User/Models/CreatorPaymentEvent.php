<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Unified ledger of all money-moving events for a creator. Tip / sub /
 * unlock / refund all write a row here so the Earnings + Payments
 * dashboards can render a single chronological feed without unioning
 * four tables on every page load.
 */
class CreatorPaymentEvent extends Model
{
    public const SOURCE_SUB = 'sub';
    public const SOURCE_PPV = 'ppv';
    public const SOURCE_TIP = 'tip';
    public const SOURCE_PRODUCT = 'product';

    public const TYPE_SUB_CREATED   = 'sub.created';
    public const TYPE_SUB_RENEWED   = 'sub.renewed';
    public const TYPE_SUB_CANCELED  = 'sub.canceled';
    public const TYPE_SUB_REFUNDED  = 'sub.refunded';
    public const TYPE_PPV_UNLOCKED  = 'ppv.unlocked';
    public const TYPE_PPV_REFUNDED  = 'ppv.refunded';
    public const TYPE_TIP_RECEIVED  = 'tip.received';
    public const TYPE_TIP_REFUNDED  = 'tip.refunded';
    public const TYPE_PRODUCT_PURCHASED = 'product.purchased';
    public const TYPE_PRODUCT_REFUNDED  = 'product.refunded';

    protected $fillable = [
        'creator_user_id', 'fan_user_id',
        'source', 'type',
        'reference_type', 'reference_id',
        'amount_cents', 'currency',
        'gateway', 'gateway_event_id',
        'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_at'  => 'datetime',
            'metadata'     => 'array',
        ];
    }

    public function creator() { return $this->belongsTo(User::class, 'creator_user_id'); }
    public function fan()     { return $this->belongsTo(User::class, 'fan_user_id'); }

    public function isCredit(): bool { return $this->amount_cents > 0; }
    public function isDebit(): bool  { return $this->amount_cents < 0; }

    public function describeShort(): string
    {
        return match ($this->type) {
            self::TYPE_SUB_CREATED  => 'New subscriber',
            self::TYPE_SUB_RENEWED  => 'Subscription renewal',
            self::TYPE_SUB_CANCELED => 'Subscription canceled',
            self::TYPE_SUB_REFUNDED => 'Subscription refund',
            self::TYPE_PPV_UNLOCKED => 'Post unlocked',
            self::TYPE_PPV_REFUNDED => 'Post unlock refunded',
            self::TYPE_TIP_RECEIVED => 'Tip received',
            self::TYPE_TIP_REFUNDED => 'Tip refunded',
            self::TYPE_PRODUCT_PURCHASED => 'Product sale',
            self::TYPE_PRODUCT_REFUNDED  => 'Product refund',
            default                 => $this->type,
        };
    }
}
