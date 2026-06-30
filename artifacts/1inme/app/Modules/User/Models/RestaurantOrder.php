<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestaurantOrder extends Model
{
    public const STATUS_NEW       = 'new';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY     = 'ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ACCEPTED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** Statuses considered "open" (still need attention). */
    public const OPEN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ACCEPTED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
    ];

    public const STATUS_LABELS = [
        self::STATUS_NEW       => 'New',
        self::STATUS_ACCEPTED  => 'Accepted',
        self::STATUS_PREPARING => 'Preparing',
        self::STATUS_READY     => 'Ready',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Allowed status transitions. Staff move an order forward through the
     * kitchen workflow and may cancel any still-open order. Terminal states
     * (completed/cancelled) can't transition further. Enforced server-side
     * so custom API clients can't jump an order to an inconsistent state.
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_NEW       => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED  => [self::STATUS_PREPARING, self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_PREPARING => [self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_READY     => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /** Whether the order may move from its current status to $next. */
    public function canTransitionTo(string $next): bool
    {
        if ($next === $this->status) {
            return true;
        }

        return in_array($next, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'menu_id', 'link_id', 'table_id', 'public_token', 'status',
        'table_label', 'customer_name', 'customer_note', 'subtotal',
        'coupon_code', 'discount_amount', 'tax_rate', 'tax_inclusive',
        'tax_amount', 'total', 'currency', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate'        => 'decimal:3',
            'tax_inclusive'   => 'boolean',
            'tax_amount'      => 'decimal:2',
            'total'           => 'decimal:2',
            'meta'            => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RestaurantOrder $order) {
            if (empty($order->public_token)) {
                $order->public_token = (string) Str::uuid();
            }
        });
    }

    public function menu()
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function table()
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function items()
    {
        return $this->hasMany(RestaurantOrderItem::class, 'order_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
