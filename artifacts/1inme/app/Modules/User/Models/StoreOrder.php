<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StoreOrder extends Model
{
    public const STATUS_NEW       = 'new';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_PACKING   = 'packing';
    public const STATUS_READY     = 'ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ACCEPTED,
        self::STATUS_PACKING,
        self::STATUS_READY,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /** Statuses considered "open" (still need attention). */
    public const OPEN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ACCEPTED,
        self::STATUS_PACKING,
        self::STATUS_READY,
    ];

    public const STATUS_LABELS = [
        self::STATUS_NEW       => 'New',
        self::STATUS_ACCEPTED  => 'Accepted',
        self::STATUS_PACKING   => 'Packing',
        self::STATUS_READY     => 'Ready',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    /**
     * Allowed status transitions. The owner moves a request forward through
     * the fulfilment workflow and may cancel any still-open request. Terminal
     * states can't transition further. Enforced server-side so custom API
     * clients can't jump a request to an inconsistent state.
     */
    public const STATUS_TRANSITIONS = [
        self::STATUS_NEW       => [self::STATUS_ACCEPTED, self::STATUS_CANCELLED],
        self::STATUS_ACCEPTED  => [self::STATUS_PACKING, self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_PACKING   => [self::STATUS_READY, self::STATUS_CANCELLED],
        self::STATUS_READY     => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    /** Whether the request may move from its current status to $next. */
    public function canTransitionTo(string $next): bool
    {
        if ($next === $this->status) {
            return true;
        }

        return in_array($next, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'menu_id', 'link_id', 'public_token', 'status',
        'customer_name', 'customer_contact', 'customer_note',
        'subtotal', 'total', 'currency', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total'    => 'decimal:2',
            'meta'     => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StoreOrder $order) {
            if (empty($order->public_token)) {
                $order->public_token = (string) Str::uuid();
            }
        });

        // Unified contact linking (Task #6501). customer_contact is a
        // free-text "phone or email" field — sniff which one it is.
        static::created(function (StoreOrder $order): void {
            $ownerId = $order->link_id
                ? Link::withoutGlobalScope('workspace')->whereKey($order->link_id)->value('user_id')
                : null;
            $contactRaw = trim((string) $order->customer_contact);
            $isEmail = $contactRaw !== '' && filter_var($contactRaw, FILTER_VALIDATE_EMAIL);
            \App\Jobs\LinkCaptureToContactJob::forRecord(
                $order,
                $ownerId ? (int) $ownerId : null,
                $isEmail ? $contactRaw : null,
                (!$isEmail && $contactRaw !== '') ? $contactRaw : null,
                $order->customer_name,
                'store_order'
            );
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function menu()
    {
        return $this->belongsTo(StoreMenu::class, 'menu_id');
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function items()
    {
        return $this->hasMany(StoreOrderItem::class, 'order_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }
}
