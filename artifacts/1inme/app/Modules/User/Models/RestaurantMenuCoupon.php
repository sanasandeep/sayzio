<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An owner-defined discount code for a single restaurant menu (Task #3067).
 *
 * Single coupon per order, no usage limits / scheduled windows / stacking —
 * just a code, a discount (percentage or fixed amount), an optional minimum
 * bill to qualify, and an active toggle.
 */
class RestaurantMenuCoupon extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED   = 'fixed';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FIXED];

    protected $fillable = [
        'menu_id', 'code', 'discount_type', 'discount_value', 'min_subtotal', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_subtotal'   => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function menu()
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }

    /** Normalise a user-entered code for case-insensitive matching. */
    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }
}
