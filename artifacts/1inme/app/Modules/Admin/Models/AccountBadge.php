<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * An admin-managed account badge (name + color). Staff create a set of
 * these and attach them to user accounts via the `account_badge_user`
 * pivot to segment / filter / bulk-action the admin user list.
 *
 * General-purpose labelling on the `users` record — unrelated to the
 * link "verification badge" (`links.is_verified`) blue checkmark.
 */
class AccountBadge extends Model
{
    protected $fillable = ['name', 'color', 'created_by'];

    /** Default badge color when none is supplied. */
    public const DEFAULT_COLOR = '#3b82f6';

    protected static function booted(): void
    {
        // When a badge is deleted, drop AI shares targeting that badge
        // group (Task #2909). Removing the badge from a user already
        // revokes access live; this clears the now-dangling share rows.
        static::deleted(function (AccountBadge $badge) {
            \App\Services\AI\AiResourceShareService::purgeForAudience(
                \App\Modules\User\Models\AiResourceShare::AUDIENCE_BADGE, (int) $badge->id
            );
        });
    }

    /** Users this badge is currently attached to. */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'account_badge_user',
            'account_badge_id',
            'user_id'
        )->withTimestamps();
    }
}
