<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Session;

/**
 * Lightweight viewer session, intentionally separate from Laravel's
 * dashboard auth guard. State is held under the `viewer_user_id`
 * session key so that signing in on a public biolink does NOT log
 * the viewer into the creator dashboard, and signing in to the
 * dashboard does not impersonate them as a follower on biolinks.
 */
class ViewerSession
{
    public const KEY = 'viewer_user_id';

    public static function login(User $user): void
    {
        Session::put(self::KEY, $user->id);
    }

    public static function logout(): void
    {
        Session::forget(self::KEY);
    }

    public static function id(): ?int
    {
        return Session::get(self::KEY);
    }

    public static function user(): ?User
    {
        $id = self::id();
        return $id ? User::find($id) : null;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }
}
