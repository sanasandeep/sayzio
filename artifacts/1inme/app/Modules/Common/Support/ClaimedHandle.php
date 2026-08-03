<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Rules\NotBannedName;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Reserve a handle a visitor typed into a "claim your link" entry point
 * (e.g. the marketing homepage hero) as a freshly-created account's @handle.
 *
 * Shared by every sign-up surface that can receive a claimed handle so the
 * validation never drifts between them:
 *   - web register form   (User\AuthController::register, desired_handle field)
 *   - OTP/WhatsApp signup  (Api\OtpController::register, desired_handle field)
 *
 * Validation mirrors CreatorProfileController::claimHandle (format +
 * case-insensitive uniqueness + admin banned-names list). Anything blank,
 * invalid, taken or banned is skipped so sign-up never dead-ends — the user
 * can pick a handle later from their profile.
 *
 * Returns null when there was nothing to do (empty input) or the handle was
 * applied successfully. When a non-empty handle could NOT be applied, returns
 * the sanitized handle the visitor asked for so the caller can surface a
 * friendly "that one was taken" notice.
 */
final class ClaimedHandle
{
    public static function apply(User $user, ?string $raw): ?string
    {
        $handle = strtolower(trim((string) $raw));
        if ($handle === '') {
            return null;
        }

        $validator = Validator::make(['handle' => $handle], [
            'handle' => [
                'string', 'min:3', 'max:30',
                'regex:/^[a-z0-9_]+$/i',
                \App\Modules\User\Models\CreatorProfile::uniqueHandleRule(),
                new NotBannedName(),
            ],
        ]);

        if ($validator->fails()) {
            return $handle;
        }

        // Task #6618 — handles live on the workspace-scoped creator profile.
        // A claimed sign-up handle lands on the new user's PERSONAL
        // workspace profile (mirrored onto users.handle for legacy readers).
        $profile = \App\Modules\User\Models\CreatorProfile::forWorkspace($user->ensureDefaultWorkspace());
        $profile->handle = $handle;
        $profile->save();
        $user->forceFill(['handle' => $handle])->save();

        return null;
    }
}
