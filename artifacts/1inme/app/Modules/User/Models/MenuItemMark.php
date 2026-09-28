<?php

namespace App\Modules\User\Models;

use App\Modules\User\Support\MenuOptionIcon;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the admin-managed vocabulary of dish marks.
 *
 * Lives in the User module rather than Admin because every public menu
 * page reads it and only one screen writes it. Admin owns the screen, the
 * menu owns the meaning.
 */
class MenuItemMark extends Model
{
    public const GROUP_DIET = 'diet';

    public const GROUP_SPICE = 'spice';

    public const GROUP_TEMPERATURE = 'temperature';

    public const GROUP_TEXTURE = 'texture';

    public const GROUP_PREFERENCE = 'preference';

    public const GROUP_ALLERGEN = 'allergen';

    public const GROUP_OTHER = 'other';

    /** The order groups are drawn in, and how the admin screen is banded. */
    public const GROUPS = [
        self::GROUP_DIET        => 'Diet',
        self::GROUP_SPICE       => 'Spice',
        self::GROUP_TEMPERATURE => 'Temperature',
        self::GROUP_TEXTURE     => 'Texture',
        self::GROUP_PREFERENCE  => 'Preference',
        self::GROUP_ALLERGEN    => 'Allergen',
        self::GROUP_OTHER       => 'Other',
    ];

    /** How many marks one dish may carry, so a row cannot become a wall. */
    public const MAX_PER_ITEM = 8;

    protected $fillable = [
        'key', 'label', 'group', 'icon', 'color', 'is_graded', 'max_grade', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_graded'  => 'boolean',
            'is_active'  => 'boolean',
            'max_grade'  => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public static function normalizeKey(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';

        return trim($key, '-');
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->group] ?? self::GROUPS[self::GROUP_OTHER];
    }

    /** A drawing, or null when this mark is a text chip. */
    public function shape(): ?array
    {
        return MenuOptionIcon::shape($this->icon);
    }

    /** The ceiling on this mark's grade: 1 when it is not graded at all. */
    public function grades(): int
    {
        if (! $this->is_graded) {
            return 1;
        }

        return max(1, min(MenuOptionIcon::MAX_REPEAT, (int) $this->max_grade));
    }
}
