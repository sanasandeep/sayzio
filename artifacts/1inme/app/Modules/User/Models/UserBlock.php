<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Viewer-side "block this creator" relationship (Task #1211).
 *
 * Blocking a creator hides their profile from the directory, removes
 * their posts from the viewer's feed, and silently drops any DM
 * thread they try to start. Unlike ViewerDmUserBlock (creator hides
 * a fan from their own DMs), the relationship here is owned by the
 * viewer and applies across surfaces.
 */
class UserBlock extends Model
{
    protected $fillable = ['blocker_user_id', 'blocked_user_id', 'reason'];

    public function blocker() { return $this->belongsTo(User::class, 'blocker_user_id'); }
    public function blocked() { return $this->belongsTo(User::class, 'blocked_user_id'); }

    /** Return the set of user-ids blocked by $blockerId (cached per request). */
    public static function blockedIdsFor(?int $blockerId): array
    {
        if (!$blockerId) return [];
        static $cache = [];
        if (isset($cache[$blockerId])) return $cache[$blockerId];
        return $cache[$blockerId] = self::where('blocker_user_id', $blockerId)
            ->pluck('blocked_user_id')->map(fn ($id) => (int) $id)->all();
    }
}
