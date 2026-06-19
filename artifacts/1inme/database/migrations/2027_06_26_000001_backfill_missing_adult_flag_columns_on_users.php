<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-go-live schema drift repair for the 18+ creator columns on `users`.
 *
 * The original Task #1208 migration
 * (2027_05_07_010000_create_creator_payment_connections) was already recorded
 * as "Ran" on the long-lived shared database back when it only added the
 * `adult_content_enabled` / `adult_content_enabled_at` columns. It was later
 * edited to also add `age_verified_at` and the moderator-suspend trio, but
 * Laravel never re-runs an already-recorded migration, so those newer columns
 * never reached environments that had already applied the earlier version.
 *
 * Code (CreatorsController, AdultModerationController, AdultContentController,
 * CreatorPayoutsApiController, User model) references the missing columns, so
 * the public /creators directory and the 18+ moderation/payout flows 500 on
 * those databases.
 *
 * This migration is purely additive and idempotent: every add is guarded by
 * `Schema::hasColumn`, so on a fresh database (where the original migration
 * already created all six columns) it is a no-op, and on a drifted database it
 * fills only the gaps.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'age_verified_at')) {
                $table->timestamp('age_verified_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'adult_flag_suspended_at')) {
                $table->timestamp('adult_flag_suspended_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'adult_flag_suspended_reason')) {
                $table->string('adult_flag_suspended_reason', 500)->nullable();
            }
            if (!Schema::hasColumn('users', 'adult_flag_suspended_by')) {
                $table->unsignedBigInteger('adult_flag_suspended_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally a no-op. The columns are owned by the original
        // create_creator_payment_connections migration's down(); dropping them
        // here would double-drop and could remove columns this migration did
        // not create on databases where the original migration added them.
    }
};
