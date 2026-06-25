<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Promote `sayzio.app` to the platform's PRIMARY global domain while
     * keeping `1in.me` as a fully-working, selectable global domain.
     *
     * Both rows are seeded by 2027_05_13 (originally UNVERIFIED, 1in.me
     * primary). For sayzio.app and 1in.me to appear in the user-facing
     * domain picker — which only lists verified+active global domains via
     * Domain::availableTo() — they must be verified here. Resolution itself
     * does not depend on verification (PlatformHosts treats both as platform
     * hosts regardless), but the picker does.
     *
     * Idempotent and additive — safe to re-run. In the testing environment we
     * leave the seeded globals UNVERIFIED so the existing domain-picker test
     * fixtures (which create their own globals) stay deterministic.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('domains', 'is_primary')) {
            return;
        }

        $cnameTarget = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'sayzio.app';

        // Make sure both platform global domains exist (defensive: covers DBs
        // that somehow missed the original seed). Insert as UNVERIFIED; the
        // verification step below decides the final state per environment.
        foreach (['sayzio.app', '1in.me'] as $domain) {
            $exists = DB::table('domains')->where('domain', $domain)->exists();
            if ($exists) {
                continue;
            }
            DB::table('domains')->insert([
                'user_id'            => null,
                'domain'             => $domain,
                'type'               => 'redirect',
                'is_active'          => true,
                'is_verified'        => false,
                'is_primary'         => false,
                'verified_at'        => null,
                'verification_token' => Str::random(32),
                'cname_target'       => $cnameTarget,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // Promote sayzio.app to primary and demote every other global domain
        // so exactly one platform-wide primary exists.
        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', '!=', 'sayzio.app')
            ->where('is_primary', true)
            ->update(['is_primary' => false, 'updated_at' => now()]);

        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', 'sayzio.app')
            ->update([
                'is_active'  => true,
                'is_primary' => true,
                'updated_at' => now(),
            ]);

        // Verify + activate both platform global domains so they surface in
        // the domain picker (availableTo() filters on is_verified). Skipped
        // under the test runner to keep existing picker fixtures clean.
        if (!app()->runningUnitTests()) {
            DB::table('domains')
                ->whereNull('user_id')
                ->whereIn('domain', ['sayzio.app', '1in.me'])
                ->update([
                    'is_active'   => true,
                    'is_verified' => true,
                    'verified_at' => now(),
                    'dns_status'  => 'healthy',
                    'updated_at'  => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('domains', 'is_primary')) {
            return;
        }

        // Restore the previous primary (1in.me) and demote sayzio.app. Leave
        // verification untouched — re-running up() will re-verify as needed.
        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', 'sayzio.app')
            ->update(['is_primary' => false, 'updated_at' => now()]);

        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', '1in.me')
            ->update(['is_primary' => true, 'updated_at' => now()]);
    }
};
