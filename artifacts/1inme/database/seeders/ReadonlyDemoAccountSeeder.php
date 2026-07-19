<?php

namespace Database\Seeders;

use App\Modules\User\Models\User;

/**
 * Task #3498 — provisions `demo@sayzio.app`: a second, publicly-safe
 * showcase account with the exact same breadth of demo content as
 * `sana@sayzio.app` (reusing {@see ShowcaseAccountSeeder}'s full content
 * pipeline unmodified), but:
 *
 *   - no `Admin` record / no admin or super-admin access at all
 *     ({@see ensureAdminBridge()} is a deliberate no-op here), and
 *   - `is_readonly_demo = true` on the user row, which the global
 *     read-only write-guard middleware uses to block every state-changing
 *     request from this account before any persistence happens.
 *
 * Strictly additive/idempotent, scoped only to this one fixed email —
 * never touches `sana@sayzio.app`, `demo@1inme.com`, or any other account.
 * Safe to call again standalone via
 * `php artisan db:seed --class=ReadonlyDemoAccountSeeder`.
 */
class ReadonlyDemoAccountSeeder extends ShowcaseAccountSeeder
{
    public const EMAIL = 'demo@sayzio.app';
    public const PASSWORD = 'ReadOnlyDemo@2026';
    public const HANDLE = 'sayziodemo';
    public const NAME = 'Sayzio Demo';
    public const BIO = 'Public read-only demo account. Explore every Sayzio feature freely. Changes you make here are never saved.';

    protected function isReadonlyDemo(): bool
    {
        return true;
    }

    /**
     * This account must be a plain user with no elevated privileges of any
     * kind — never the `user-admin` web role that {@see ShowcaseAccountSeeder}
     * grants its normal showcase account (Task #3498, step 3).
     */
    protected function shouldAssignUserAdminRole(): bool
    {
        return false;
    }

    /**
     * Deliberately does nothing: this account must never get an Admin
     * record or admin/super-admin roles (Task #3498, step 3). Also cleans
     * up any stale `Admin` row so re-running this seeder after an admin
     * bridge existed (e.g. before this guard was added) removes it.
     */
    protected function ensureAdminBridge(User $user): void
    {
        \App\Modules\Admin\Models\Admin::where('email', static::EMAIL)->delete();
    }
}
