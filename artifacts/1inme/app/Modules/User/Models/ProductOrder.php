<?php

namespace App\Modules\User\Models;

use App\Modules\Common\Models\ViewerDmConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single combined product checkout. One order may contain many
 * product_order_items, but always from a single creator (multi-creator
 * carts are out of scope). Amounts are gross cents — platform takes 0%.
 */
class ProductOrder extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    protected $fillable = [
        'buyer_user_id', 'creator_user_id', 'link_id',
        'status', 'subtotal_cents', 'currency',
        'gateway', 'gateway_charge_id',
        'contains_physical', 'contains_digital',
        'conversation_id', 'public_token',
        'paid_at', 'fulfilled_at', 'refunded_at', 'refund_reason', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents'    => 'integer',
            'contains_physical' => 'boolean',
            'contains_digital'  => 'boolean',
            'paid_at'           => 'datetime',
            'fulfilled_at'      => 'datetime',
            'refunded_at'       => 'datetime',
            'metadata'          => 'array',
        ];
    }

    /**
     * Unified contact linking (Task #6501): the buyer is a real Sayzio user,
     * so resolve their account email/phone into the creator's Contact.
     */
    protected static function booted(): void
    {
        static::created(function (ProductOrder $order): void {
            if (!$order->creator_user_id || !$order->buyer_user_id) {
                return;
            }
            $buyer = User::find($order->buyer_user_id);
            if (!$buyer) {
                return;
            }
            \App\Jobs\LinkCaptureToContactJob::forRecord(
                $order,
                (int) $order->creator_user_id,
                $buyer->email,
                $buyer->mobile,
                $buyer->name,
                'product_order'
            );
        });
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class, 'order_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'link_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ViewerDmConversation::class, 'conversation_id');
    }

    public function isPaid(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_FULFILLED], true);
    }

    /**
     * Only a still-paid order (not already refunded/cancelled) can be
     * refunded. Pending orders never charged the buyer.
     */
    public function isRefundable(): bool
    {
        return $this->isPaid();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING   => 'Pending',
            self::STATUS_PAID      => 'Paid',
            self::STATUS_FULFILLED => 'Fulfilled',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED  => 'Refunded',
            default                => ucfirst((string) $this->status),
        };
    }
}
