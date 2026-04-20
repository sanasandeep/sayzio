<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-identity linking — each user can have multiple verified
 * identifiers (emails, phone numbers, social-provider identities)
 * attached, any one of which can be used to sign in.
 *
 * Backfills each existing user's current email/mobile and any
 * SocialAccountConnection rows as their initial linked identifiers,
 * marking the email as primary (or mobile if no email).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linked_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 'email' | 'phone' | 'social'
            $table->string('kind', 16);

            // For email/phone this holds the address/number.
            // For social this holds a stable composite "<provider>:<external_id>"
            // (or "<provider>:<handle>" if no external_id) so the unique
            // constraint prevents the same provider account being linked twice.
            $table->string('value');

            // Social-only: provider name (instagram, facebook, twitter, ...).
            $table->string('provider', 32)->nullable();

            // Social-only: provider's stable external id.
            $table->string('external_id')->nullable();

            // When the user proved control of this identifier.
            $table->timestamp('verified_at')->nullable();

            // Exactly one identifier per user is the primary.
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // A verified identifier can only be attached to one live account.
            $table->unique(['kind', 'value']);
            $table->index(['user_id', 'kind']);
        });

        // Backfill existing users.
        $now = now();
        $hasMobile = Schema::hasColumn('users', 'mobile');
        $rows = DB::table('users')->select('id', 'email', $hasMobile ? 'mobile' : DB::raw('NULL as mobile'))->get();
        // Use the same normalisation rules the runtime model uses, so a
        // legacy phone like "+1 555 123 4567" backfills to the canonical
        // form "+15551234567" that runtime lookups will produce.
        $normalizePhone = function (?string $raw): string {
            $raw = trim((string) $raw);
            if ($raw === '') return $raw;
            $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', $raw);
            return $cleaned ?? $raw;
        };
        foreach ($rows as $u) {
            $primaryAssigned = false;
            if (!empty($u->email)) {
                DB::table('linked_identifiers')->insertOrIgnore([
                    'user_id'    => $u->id,
                    'kind'       => 'email',
                    'value'      => strtolower(trim($u->email)),
                    'verified_at'=> $now,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $primaryAssigned = true;
            }
            if (!empty($u->mobile)) {
                DB::table('linked_identifiers')->insertOrIgnore([
                    'user_id'    => $u->id,
                    'kind'       => 'phone',
                    'value'      => $normalizePhone($u->mobile),
                    'verified_at'=> $now,
                    'is_primary' => !$primaryAssigned,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $primaryAssigned = true;
            }
        }

        // Backfill social connections.
        if (Schema::hasTable('social_account_connections')) {
            $socials = DB::table('social_account_connections')
                ->select('user_id', 'platform', 'external_id', 'handle')
                ->get();
            foreach ($socials as $s) {
                $ext = $s->external_id ?: $s->handle;
                if (!$ext) continue;
                DB::table('linked_identifiers')->insertOrIgnore([
                    'user_id'    => $s->user_id,
                    'kind'       => 'social',
                    'value'      => $s->platform . ':' . $ext,
                    'provider'   => $s->platform,
                    'external_id'=> (string) $ext,
                    'verified_at'=> $now,
                    'is_primary' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('linked_identifiers');
    }
};
