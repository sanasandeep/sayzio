<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TaskBoard extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'created_by_user_id', 'scope', 'owner_user_id',
        'name', 'slug', 'color', 'description', 'position', 'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function columns()
    {
        return $this->hasMany(TaskColumn::class, 'board_id')->orderBy('position');
    }

    public function cards()
    {
        return $this->hasMany(TaskCard::class, 'board_id');
    }

    public function labels()
    {
        return $this->hasMany(TaskLabel::class, 'board_id')->orderBy('name');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isPersonal(): bool
    {
        return $this->scope === 'personal';
    }

    /** Whether the given user can see this board at all. */
    public function visibleTo(User $user): bool
    {
        if ($this->scope === 'personal') {
            return (int) $this->owner_user_id === (int) $user->id;
        }
        return true;
    }

    protected static function booted(): void
    {
        static::creating(function (self $board) {
            if (empty($board->slug)) {
                $board->slug = static::uniqueSlug($board->name ?: 'board');
            }
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $base = Str::slug(Str::limit($base, 50, '')) ?: Str::random(8);
        $slug = $base;
        $i = 1;
        while (static::query()->withoutGlobalScope('workspace')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
