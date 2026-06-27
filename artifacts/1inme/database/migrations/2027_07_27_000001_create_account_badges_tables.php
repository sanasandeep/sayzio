<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed account badges: a general-purpose, staff-only labelling
 * system for user accounts. Staff define a set of badges (name + color)
 * and attach one or more to a user to segment / filter / bulk-action the
 * admin user list. Distinct from the link "verification badge" system
 * (`links.is_verified`) — these live on the `users` record.
 *
 * Additive + idempotent (shared RDS, additive-only): every create is
 * guarded by hasTable, every column by hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_badges')) {
            Schema::create('account_badges', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 60);
                // Hex color (e.g. #3b82f6) used to render the badge pill.
                $table->string('color', 9)->default('#3b82f6');
                // Operator (admin guard) who created the badge, nullable so a
                // CLI/system create or a later-deleted admin still reads cleanly.
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique('name', 'account_badges_name_unique');
            });
        }

        if (!Schema::hasTable('account_badge_user')) {
            Schema::create('account_badge_user', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('account_badge_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->unique(['account_badge_id', 'user_id'], 'account_badge_user_unique');
                $table->index('user_id', 'account_badge_user_user_idx');

                $table->foreign('account_badge_id', 'abu_badge_fk')
                    ->references('id')->on('account_badges')->cascadeOnDelete();
                $table->foreign('user_id', 'abu_user_fk')
                    ->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_badge_user');
        Schema::dropIfExists('account_badges');
    }
};
