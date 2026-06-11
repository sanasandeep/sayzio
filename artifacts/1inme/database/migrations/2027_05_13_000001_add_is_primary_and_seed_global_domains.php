<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-controllable "primary" flag for global domains. Only a
        // global domain (user_id = null) is ever marked primary, and at
        // most one row is primary at a time (enforced by the model /
        // controller). The primary global domain becomes the pre-selected
        // default when users create new short links and biolinks.
        if (!Schema::hasColumn('domains', 'is_primary')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('is_active');
            });
        }

        // Seed 1in.me and sayzio.app as active global domains (no owning
        // user, untagged so open to every plan). Left UNVERIFIED by default
        // so the admin uses the existing Verify / force-verify flow, exactly
        // like any admin-added global domain. Idempotent: skip rows that
        // already exist by domain name.
        $cnameTarget = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '1inme.com';

        foreach (['1in.me', 'sayzio.app'] as $domain) {
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
    }

    public function down(): void
    {
        DB::table('domains')
            ->whereNull('user_id')
            ->whereIn('domain', ['1in.me', 'sayzio.app'])
            ->delete();

        if (Schema::hasColumn('domains', 'is_primary')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropColumn('is_primary');
            });
        }
    }
};
