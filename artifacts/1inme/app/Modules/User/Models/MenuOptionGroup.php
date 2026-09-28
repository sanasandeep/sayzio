<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A set of choices on a menu item: spice level, size, toppings, add-ons.
 *
 * One definition for both menu types -- `menu_type` says which menu table
 * `menu_id` points into. See the migration for why these are not four
 * separate features and not JSON on the item.
 */
class MenuOptionGroup extends Model
{
    public const RESTAURANT = 'restaurant';
    public const STORE = 'store';

    protected $fillable = [
        'menu_type', 'menu_id', 'name', 'hint',
        'is_required', 'min_select', 'max_select', 'max_per_option',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required'    => 'boolean',
            'is_active'      => 'boolean',
            'min_select'     => 'integer',
            'max_select'     => 'integer',
            'max_per_option' => 'integer',
            'sort_order'     => 'integer',
        ];
    }

    public function options()
    {
        return $this->hasMany(MenuOption::class, 'group_id')->orderBy('sort_order')->orderBy('id');
    }

    public function attachments()
    {
        return $this->hasMany(MenuItemOptionGroup::class, 'group_id');
    }

    /** The menu type string for a menu model, so callers don't spell it out. */
    public static function typeFor(RestaurantMenu|StoreMenu $menu): string
    {
        return $menu instanceof RestaurantMenu ? self::RESTAURANT : self::STORE;
    }

    /**
     * How many choices a guest must and may make, after the flags are
     * reconciled with each other.
     *
     * `is_required` and `min_select` overlap: a creator who ticks "required"
     * and leaves the minimum at 0 means "at least one", and one who sets a
     * minimum of 2 without ticking required has still made it required.
     * Reading them separately is how a page ends up letting a guest skip a
     * group that says it is required.
     *
     * @return array{min:int, max:?int}
     */
    public function bounds(): array
    {
        $min = max(0, (int) $this->min_select);
        if ($this->is_required && $min < 1) {
            $min = 1;
        }

        $max = $this->max_select === null ? null : max(1, (int) $this->max_select);
        // A ceiling below the floor is a typo, not an instruction to make
        // the group impossible to satisfy.
        if ($max !== null && $max < $min) {
            $max = $min;
        }

        return ['min' => $min, 'max' => $max];
    }

    /** Whether picking is effectively mandatory, however the creator said it. */
    public function isRequired(): bool
    {
        return $this->bounds()['min'] > 0;
    }

    /** How many times one choice may be taken. Always at least once. */
    public function perOptionCap(): int
    {
        return max(1, (int) $this->max_per_option);
    }
}
