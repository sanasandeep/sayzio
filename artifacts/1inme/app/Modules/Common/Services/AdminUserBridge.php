<?php

namespace App\Modules\Common\Services;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the trusted bridge between a web-guard {@see User} and a
 * back-office {@see Admin} record.
 *
 * SECURITY — this bridge is what turns an ordinary user into an operator
 * for the dashboard switch and the mobile admin API, so it must NOT be
 * derivable from a mutable, user-controlled field. Historically the two
 * auth pools shared no foreign key and were matched purely by a
 * case-insensitive email string. That let anyone who registered (or
 * renamed) a user to an admin's email inherit that admin's authority
 * without ever proving they controlled the mailbox — a full
 * privilege-escalation across the user/admin boundary.
 *
 * The trust anchor is now an explicit, immutable link column
 * (`admins.user_id`). A link is only ever established when the user has
 * PROVEN control of the email address (`email_verified_at` is set) and
 * that address uniquely matches a single, still-unlinked admin.
 * Verification is the ownership proof: an attacker who merely typed an
 * admin's email but cannot receive its mail can never reach
 * `email_verified_at`, so no link is created and no privilege is granted.
 * Once bound, later email mutations cannot retarget the link.
 *
 * The verified-email fallback (used only when the link column has not been
 * added yet, e.g. an isolated env whose migrations are still catching up)
 * carries the same ownership gate and never persists, so it can never
 * bridge an unverified collision either.
 */
class AdminUserBridge
{
    /** Positive-only per-process cache: the link column never disappears. */
    private static bool $linkColumnAvailable = false;

    private static function linkColumnAvailable(): bool
    {
        if (self::$linkColumnAvailable) {
            return true;
        }
        try {
            $has = Schema::hasColumn('admins', 'user_id');
        } catch (\Throwable $e) {
            $has = false;
        }
        if ($has) {
            self::$linkColumnAvailable = true;
        }
        return $has;
    }

    /** True only when the user has proven ownership of its current email. */
    private static function ownsEmail(User $user): bool
    {
        return $user->email_verified_at !== null
            && strtolower(trim((string) $user->email)) !== '';
    }

    /**
     * The Admin record (active or not) bound to this user, or null when no
     * trusted binding can be resolved.
     */
    public static function resolveAdminForUser(User $user): ?Admin
    {
        try {
            $email = strtolower(trim((string) $user->email));
            if ($email === '' || ! $user->getKey()) {
                return null;
            }

            if (self::linkColumnAvailable()) {
                $linked = Admin::query()->where('user_id', $user->getKey())->first();
                if ($linked !== null) {
                    return $linked;
                }

                // No link yet: only establish one under proof of ownership.
                if (! self::ownsEmail($user)) {
                    return null;
                }

                $matches = Admin::query()
                    ->whereNull('user_id')
                    ->whereRaw('lower(email) = ?', [$email])
                    ->limit(2)
                    ->get();
                if ($matches->count() !== 1) {
                    return null; // ambiguous or none -> fail closed
                }

                $admin = $matches->first();
                // Race-safe claim: only bind while the admin is still unlinked.
                Admin::query()
                    ->whereKey($admin->getKey())
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->getKey()]);
                $admin->user_id = $user->getKey();

                return $admin;
            }

            // Pre-migration fallback: verified email match only, no persist.
            if (! self::ownsEmail($user)) {
                return null;
            }

            return Admin::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The User bound to this admin, or null. Mirrors
     * {@see self::resolveAdminForUser()} from the admin side.
     */
    public static function resolveUserForAdmin(Admin $admin): ?User
    {
        try {
            if (self::linkColumnAvailable() && $admin->user_id) {
                $user = User::query()->whereKey($admin->user_id)->first();
                if ($user !== null) {
                    return $user;
                }
            }

            $email = strtolower(trim((string) $admin->email));
            if ($email === '') {
                return null;
            }

            $matches = User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->whereNotNull('email_verified_at')
                ->limit(2)
                ->get();
            if ($matches->count() !== 1) {
                return null; // ambiguous or unverified -> fail closed
            }
            $user = $matches->first();

            if (self::linkColumnAvailable() && ! $admin->user_id) {
                Admin::query()
                    ->whereKey($admin->getKey())
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->getKey()]);
                $admin->user_id = $user->getKey();
            }

            return $user;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
