<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One serving from a bulk order, redeemable once.
 *
 * Sana, 2026-10-04: "when bulk order.... need to generate food coupons
 * ids.... when coupoon id give, order of that 1 coupon is completed".
 */
class MenuOrderCoupon extends Model
{
    public const RESTAURANT = 'restaurant';

    public const STORE = 'store';

    public const ISSUED = 'issued';

    public const REDEEMED = 'redeemed';

    public const VOID = 'void';

    /**
     * No 0/O, no 1/I/L. A code is read off a phone screen and typed by
     * somebody at a serving counter, often at speed, and those four
     * characters are where that goes wrong.
     */
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LENGTH = 8;

    /** More than this on one order is a mistake, not a canteen. */
    public const MAX_PER_ORDER = 2000;

    protected $table = 'menu_order_coupons';

    protected $fillable = [
        'code', 'order_type', 'order_id', 'order_item_id',
        'menu_type', 'menu_id', 'item_name',
        'status', 'redeemed_at', 'redeemed_by',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * What somebody typed, as a code we can look up.
     *
     * Uppercase, and the separator and any spaces removed: people type the
     * dash they were shown, and people type lowercase.
     *
     * Deliberately NO character folding. The obvious next step is to map
     * O to 0 and I to 1 so a misread still finds the coupon -- but this
     * alphabet already excludes 0, O, 1, I and L for exactly that reason,
     * so there is nothing left to fold that is not a real character. S and
     * 5 are both valid here, and folding them would make two different
     * coupons resolve to the same row, which is worse than asking someone
     * to read their code again.
     */
    public static function normalize(?string $raw): string
    {
        $value = strtoupper(trim((string) $raw));

        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    /** A code nobody can guess from the one next to it. */
    public static function mint(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;

        do {
            $code = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $code .= $alphabet[random_int(0, $max)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /** How the code is shown: two groups, which is how people read it back. */
    public function display(): string
    {
        return trim(implode('-', str_split($this->code, 4)), '-');
    }

    public function isRedeemable(): bool
    {
        return $this->status === self::ISSUED;
    }

    public function order()
    {
        return $this->order_type === self::RESTAURANT
            ? RestaurantOrder::find($this->order_id)
            : StoreOrder::find($this->order_id);
    }
}
