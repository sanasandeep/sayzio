<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $oldEmail = 'admin@1inme.com';
    private string $newEmail = 'official1inme@gmail.com';

    /**
     * Re-provision the primary super-admin account so existing
     * environments use `official1inme@gmail.com` instead of the legacy
     * `admin@1inme.com`. Idempotent: if the new email already exists we
     * leave it untouched (and drop the stale legacy row if any), otherwise
     * we rename the legacy row in place so the super-admin role is kept.
     */
    public function up(): void
    {
        $newExists = DB::table('admins')->where('email', $this->newEmail)->exists();
        $oldExists = DB::table('admins')->where('email', $this->oldEmail)->exists();

        if ($newExists) {
            if ($oldExists) {
                DB::table('admins')->where('email', $this->oldEmail)->delete();
            }
            return;
        }

        if ($oldExists) {
            DB::table('admins')
                ->where('email', $this->oldEmail)
                ->update(['email' => $this->newEmail]);
        }
    }

    /**
     * Best-effort revert: rename back only if the new email exists and the
     * legacy one does not, to avoid creating duplicates.
     */
    public function down(): void
    {
        $newExists = DB::table('admins')->where('email', $this->newEmail)->exists();
        $oldExists = DB::table('admins')->where('email', $this->oldEmail)->exists();

        if ($newExists && !$oldExists) {
            DB::table('admins')
                ->where('email', $this->newEmail)
                ->update(['email' => $this->oldEmail]);
        }
    }
};
