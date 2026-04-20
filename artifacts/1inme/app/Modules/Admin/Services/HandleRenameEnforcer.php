<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Models\BannedNameAcknowledgement;
use App\Modules\User\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Decides whether a just-signed-in user must immediately pick a new
 * handle. Centralised so every login flow (OTP, social OAuth, future
 * SSO providers) can call it the same way without coupling controllers.
 */
class HandleRenameEnforcer
{
    /**
     * If the user's current handle matches a banned-name entry that
     * has "force rename on login" enabled (and has not been
     * acknowledged), set the prompt-session flag and return a redirect
     * to the profile-edit page. Returns null when no enforcement
     * applies — the caller should then continue with its normal post-
     * login redirect.
     */
    public static function maybeRedirect(User $user): ?RedirectResponse
    {
        if (empty($user->handle)) return null;

        $lc = mb_strtolower($user->handle);
        $banned = BannedName::whereRaw('LOWER(name) = ?', [$lc])
            ->where('force_rename_on_login', true)
            ->first();
        if (!$banned) return null;

        $acked = BannedNameAcknowledgement::where('banned_name_id', $banned->id)
            ->where('conflict_type', 'user')
            ->where('conflict_id', $user->id)
            ->exists();
        if ($acked) return null;

        session(['force_handle_rename' => $banned->name]);
        return redirect()->route('user.profile.edit')
            ->with('error', "Your handle (@{$user->handle}) is no longer available. Please pick a new one to continue.");
    }
}
