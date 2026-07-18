<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $typoEmail     = 'sazioapp@gmail.com';
    private string $canonicalEmail = 'sayzioapp@gmail.com';

    /**
     * Rename the demo-login web user from the misspelled `sazioapp@gmail.com`
     * to the correctly-spelled `sayzioapp@gmail.com`.
     *
     * Idempotent strategy
     * -------------------
     * Case A — canonical email already exists in `users`:
     *   Keep it; drop the typo'd row (if present) to avoid duplicates.
     *
     * Case B — only the typo'd row exists:
     *   Rename it in place so `is_demo`, roles, and all FK relations are
     *   preserved.
     *
     * Case C — neither row exists: nothing to do.
     */
    public function up(): void
    {
        $now       = now();
        $canonical = $this->canonicalEmail;
        $typo      = $this->typoEmail;

        $canonicalExists = DB::table('users')
            ->whereRaw('lower(email) = ?', [strtolower($canonical)])
            ->exists();

        if ($canonicalExists) {
            // Case A: canonical already present — drop the stale typo'd row.
            DB::table('users')
                ->whereRaw('lower(email) = ?', [strtolower($typo)])
                ->delete();
            return;
        }

        // Case B/C: rename the typo'd row if it exists.
        DB::table('users')
            ->whereRaw('lower(email) = ?', [strtolower($typo)])
            ->update([
                'email'      => $canonical,
                'updated_at' => $now,
            ]);
    }

    /**
     * Best-effort revert: rename back only when safe (canonical exists, typo
     * does not). On shared/production DBs this may be a no-op by design.
     */
    public function down(): void
    {
        $canonicalExists = DB::table('users')
            ->whereRaw('lower(email) = ?', [strtolower($this->canonicalEmail)])
            ->exists();
        $typoExists = DB::table('users')
            ->whereRaw('lower(email) = ?', [strtolower($this->typoEmail)])
            ->exists();

        if ($canonicalExists && !$typoExists) {
            DB::table('users')
                ->whereRaw('lower(email) = ?', [strtolower($this->canonicalEmail)])
                ->update(['email' => $this->typoEmail]);
        }
    }
};
