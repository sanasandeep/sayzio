<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3606 — free RSVP attendees get their own QR check-in ticket, just
 * like paid tier ticket buyers. `event_tickets.tier_id` was NOT NULL
 * (tier-only ticketing); this makes it nullable and adds a nullable
 * `rsvp_id` FK so an RSVP-issued ticket can exist without a tier.
 *
 * No doctrine/dbal is installed, so Blueprint::change() isn't available —
 * the NOT NULL drop is done via a raw, idempotent ALTER COLUMN statement
 * instead. Fully additive/guarded: safe to re-run against a
 * partially-migrated DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_tickets') && Schema::hasColumn('event_tickets', 'tier_id')) {
            $isNullable = DB::selectOne(
                "select is_nullable from information_schema.columns where table_name = 'event_tickets' and column_name = 'tier_id'"
            );
            if ($isNullable && $isNullable->is_nullable === 'NO') {
                DB::statement('ALTER TABLE event_tickets ALTER COLUMN tier_id DROP NOT NULL');
            }
        }

        if (Schema::hasTable('event_tickets') && !Schema::hasColumn('event_tickets', 'rsvp_id')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->foreignId('rsvp_id')->nullable()->after('tier_id')->constrained('rsvps')->nullOnDelete();
                $table->index(['rsvp_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_tickets') && Schema::hasColumn('event_tickets', 'rsvp_id')) {
            Schema::table('event_tickets', function (Blueprint $table) {
                $table->dropConstrainedForeignId('rsvp_id');
            });
        }
        // Restoring the NOT NULL on tier_id is intentionally skipped: any
        // RSVP-issued (tier-less) tickets created since this migration
        // ran would violate it, and rolling back ticketing entirely is
        // handled by the original create-tables migration's down().
    }
};
