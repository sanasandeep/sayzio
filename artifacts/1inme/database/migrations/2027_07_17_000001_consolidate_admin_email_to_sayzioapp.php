<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $canonical   = 'sayzioapp@gmail.com';
    private array  $legacyAdmins = [
        'sanasandeep@gmail.com',
        'official1inme@gmail.com',
        'admin@1inme.com',
    ];
    private array  $legacyProtected = [
        'sanasandeep@gmail.com',
        'official1inme@gmail.com',
        'admin@1inme.com',
    ];

    /**
     * Consolidate all privileged admin identities under `sayzioapp@gmail.com`.
     *
     * Idempotent strategy
     * -------------------
     * Case A — canonical row already exists in `admins`:
     *   Keep it, drop every legacy row by its emails.
     *
     * Case B — canonical row absent:
     *   Find the first super-admin row among the legacy emails (preferring
     *   `sanasandeep@gmail.com`), rename it to the canonical email, then
     *   drop any remaining legacy rows.
     *
     * In both cases also reconcile `protected_accounts`:
     *   - Remove old entries keyed to the retired emails.
     *   - Upsert the canonical email as the locked "Superadmin" entry.
     */
    public function up(): void
    {
        $now       = now();
        $canonical = $this->canonical;

        $canonicalAdmin = DB::table('admins')
            ->whereRaw('lower(email) = ?', [strtolower($canonical)])
            ->first();

        if ($canonicalAdmin) {
            // Case A: canonical already exists — just prune stale legacy rows.
            foreach ($this->legacyAdmins as $legacy) {
                DB::table('admins')
                    ->whereRaw('lower(email) = ?', [strtolower($legacy)])
                    ->delete();
            }
        } else {
            // Case B: find the best legacy super-admin to rename.
            $superAdminRoleId = DB::table('roles')
                ->where('slug', 'super-admin')
                ->where('guard', 'admin')
                ->value('id');

            // Prefer sanasandeep@gmail.com, then official1inme@gmail.com.
            $preferred = null;
            foreach ($this->legacyAdmins as $legacy) {
                $row = DB::table('admins')
                    ->whereRaw('lower(email) = ?', [strtolower($legacy)])
                    ->first();
                if ($row) {
                    $preferred = $row;
                    break;
                }
            }

            if ($preferred) {
                // Rename the chosen row to the canonical email.
                DB::table('admins')
                    ->where('id', $preferred->id)
                    ->update([
                        'email'      => $canonical,
                        'role_id'    => $superAdminRoleId ?: $preferred->role_id,
                        'status'     => 'active',
                        'updated_at' => $now,
                    ]);

                // Drop any remaining legacy rows (all except the one we just renamed).
                foreach ($this->legacyAdmins as $legacy) {
                    if (strtolower($legacy) !== strtolower($preferred->email)) {
                        DB::table('admins')
                            ->whereRaw('lower(email) = ?', [strtolower($legacy)])
                            ->delete();
                    }
                }
            }
        }

        // Reconcile protected_accounts: remove retired entries, upsert canonical.
        foreach ($this->legacyProtected as $legacy) {
            DB::table('protected_accounts')
                ->whereRaw('lower(email) = ?', [strtolower($legacy)])
                ->delete();
        }

        DB::table('protected_accounts')->updateOrInsert(
            ['email' => $canonical],
            [
                'locked'     => true,
                'label'      => 'Superadmin',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    /**
     * Best-effort revert: not safe to fully invert on a shared/production DB
     * (we do not know which legacy row was the "original"). This is a no-op.
     */
    public function down(): void
    {
        // No-op by design.
    }
};
