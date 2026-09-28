<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Which items a MenuOptionGroup applies to.
 *
 * `owner_type` is 'restaurant_item' or 'store_product'; `sort_order` is the
 * order the groups appear in on THIS item, because one dish may want sides
 * before spice and another the other way round.
 */
class MenuItemOptionGroup extends Model
{
    public const RESTAURANT_ITEM = 'restaurant_item';
    public const STORE_PRODUCT = 'store_product';

    protected $table = 'menu_item_option_groups';

    protected $fillable = ['group_id', 'owner_type', 'owner_id', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function group()
    {
        return $this->belongsTo(MenuOptionGroup::class, 'group_id');
    }

    /** The owner_type string for an item model, so callers don't spell it out. */
    public static function typeFor(RestaurantMenuItem|StoreProduct $item): string
    {
        return $item instanceof RestaurantMenuItem ? self::RESTAURANT_ITEM : self::STORE_PRODUCT;
    }
}
