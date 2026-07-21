<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\VerificationTickType;
use Illuminate\Support\Facades\DB;

/**
 * Shared approve/reject cores for profile-level verification moderation
 * (Task #5439). Both the web reviewer screens
 * (`/user/profile-verification-admin`) and the REST API
 * (`/api/v1/admin/profile-verification/*`) delegate here so the status
 * transitions, user-column updates and notifications cannot drift.
 */
class ProfileVerificationModeration
{
    /**
     * Approve a pending request: stamp the request, grant/refresh the
     * user's verified status + tick, then notify the user.
     *
     * @param array{admin_notes?:?string, tick_type_id?:int|null} $data
     */
    public static function approve(ProfileVerificationRequest $req, array $data, int $reviewerId): void
    {
        $user = $req->user;

        DB::transaction(function () use ($req, $user, $data, $reviewerId) {
            $req->update([
                'status'       => 'approved',
                'admin_notes'  => $data['admin_notes'] ?? null,
                'reviewed_at'  => now(),
                'reviewed_by'  => $reviewerId,
            ]);

            $tickId = $data['tick_type_id'] ?? $req->tick_type_id;

            $updates = [
                'profile_verification_status'   => 'verified',
                'profile_verification_type_id'  => $tickId,
                'profile_verified_at'           => now(),
            ];

            if ($req->kind === 'new') {
                $updates['profile_verified_name']   = $req->official_name;
                $updates['profile_verified_avatar'] = $req->logo_path;
            } else {
                // Re-verification: apply the approved name/avatar change
                if ($req->new_name)   $updates['profile_verified_name']   = $req->new_name;
                if ($req->new_avatar) $updates['profile_verified_avatar'] = $req->new_avatar;
            }

            $user->update($updates);
        });

        $tickName     = optional(VerificationTickType::find($data['tick_type_id'] ?? $req->tick_type_id))->name ?? 'verified';
        $verifiedName = (string) ($user->fresh()->profile_verified_name ?? $req->official_name);
        app(\App\Modules\Admin\Services\UserAccountNotifier::class)
            ->verificationApproved($user, $tickName, $verifiedName, $req->kind !== 'new');
    }

    /**
     * Reject a request: stamp it, revert the user's status (new →
     * unverified, re-verification → back to verified), notify the user.
     *
     * @param array{admin_notes:string} $data
     */
    public static function reject(ProfileVerificationRequest $req, array $data, int $reviewerId): void
    {
        $user = $req->user;

        DB::transaction(function () use ($req, $user, $data, $reviewerId) {
            $req->update([
                'status'      => 'rejected',
                'admin_notes' => $data['admin_notes'],
                'reviewed_at' => now(),
                'reviewed_by' => $reviewerId,
            ]);

            // On rejection, revert the user's status
            if ($req->kind === 'new') {
                $user->update(['profile_verification_status' => 'unverified']);
            } else {
                // Re-verification rejected → revert to fully verified
                $user->update(['profile_verification_status' => 'verified']);
            }
        });

        app(\App\Modules\Admin\Services\UserAccountNotifier::class)
            ->verificationRejected($user, $data['admin_notes'], $req->kind !== 'new');
    }
}
