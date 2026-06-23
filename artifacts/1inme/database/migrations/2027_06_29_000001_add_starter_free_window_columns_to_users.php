<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Starter (free) plan 1-year free window.
 *
 * The default Starter plan never charges, but creators are asked to
 * re-confirm once a year that they still want it. These two columns drive
 * that gentle, reminder-only cadence (no lockout / no downgrade ever):
 *   - `starter_free_window_ends_at` — when the current free year lapses.
 *     A one-click "renew free another year" action just pushes this out
 *     by 12 months.
 *   - `starter_renewal_reminder_sent_at` — dedupe stamp so the scheduled
 *     reminder fires at most once per free window (and again only after a
 *     renewal advances the window).
 *
 * Additive + idempotent: guarded by hasColumn so it is safe to re-run on
 * the shared RDS (which is never wiped / migrate:fresh'd).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'starter_free_window_ends_at')) {
                $table->timestamp('starter_free_window_ends_at')->nullable()->after('plan_expires_at');
            }
            if (!Schema::hasColumn('users', 'starter_renewal_reminder_sent_at')) {
                $table->timestamp('starter_renewal_reminder_sent_at')->nullable()->after('starter_free_window_ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'starter_renewal_reminder_sent_at')) {
                $table->dropColumn('starter_renewal_reminder_sent_at');
            }
            if (Schema::hasColumn('users', 'starter_free_window_ends_at')) {
                $table->dropColumn('starter_free_window_ends_at');
            }
        });
    }
};
