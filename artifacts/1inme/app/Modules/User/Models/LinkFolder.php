<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LinkFolder extends Model
{
    use BelongsToWorkspace;

    /** Preset palette shown in the folder color picker (web + mobile). */
    public const COLORS = [
        'blue', 'purple', 'pink', 'red', 'orange', 'yellow', 'green', 'teal', 'gray',
    ];

    protected $fillable = ['user_id', 'name', 'color'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function links(): BelongsToMany
    {
        return $this->belongsToMany(Link::class, 'link_folder_links')
            ->withTimestamps();
    }

    public static function normalizeColor(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return in_array($color, self::COLORS, true) ? $color : 'blue';
    }
}
