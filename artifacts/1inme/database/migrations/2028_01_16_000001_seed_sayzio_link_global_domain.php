<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $domain = 'sayzio.link';
        $cnameTarget = parse_url((string) config('app.url'), PHP_URL_HOST) ?: '1in.me';

        $existing = DB::table('domains')->where('domain', $domain)->first();

        if ($existing) {
            // Normalize to global + active + verified + redirect type; leave is_primary untouched.
            DB::table('domains')->where('domain', $domain)->update([
                'user_id'      => null,
                'type'         => 'redirect',
                'is_active'    => true,
                'is_verified'  => true,
                'verified_at'  => $existing->verified_at ?? now(),
                'cname_target' => $existing->cname_target ?? $cnameTarget,
                'updated_at'   => now(),
            ]);
            $id = $existing->id;
        } else {
            $id = DB::table('domains')->insertGetId([
                'user_id'            => null,
                'domain'             => $domain,
                'type'               => 'redirect',
                'is_active'          => true,
                'is_verified'        => true,
                'is_primary'         => false,
                'verified_at'        => now(),
                'verification_token' => Str::random(32),
                'cname_target'       => $cnameTarget,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        // Remove any plan or badge restrictions so the domain is open to all.
        DB::table('domain_plan')->where('domain_id', $id)->delete();
        DB::table('account_badge_domain')->where('domain_id', $id)->delete();
    }

    public function down(): void
    {
        DB::table('domains')
            ->whereNull('user_id')
            ->where('domain', 'sayzio.link')
            ->delete();
    }
};
