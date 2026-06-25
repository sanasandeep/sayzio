<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #1208 — Multi-processor creator payouts + NSFW consent / age gate.
 *
 *  - Adds the per-creator `creator_payment_connections` table that
 *    holds one row per provider hookup (Stripe Connect, PayPal, Razorpay
 *    Route, CCBill, Segpay). One row may be flagged is_default for
 *    routing future paid features through.
 *
 *  - Extends `users` with adult-content (18+) opt-in metadata: an
 *    enable flag, the timestamp of the last age + identity affirmation
 *    (audit trail), and a moderator-suspend pair so admins can pause a
 *    creator's adult flag without deleting the consent history.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('creator_payment_connections')) {
            Schema::create('creator_payment_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                // Provider slug — see PayoutProviderRegistry::PROVIDERS.
                $table->string('provider', 32);
                // Returned by the provider's onboarding handoff.
                $table->string('account_id', 191)->nullable();
                // Two-letter ISO country the creator selected on onboarding.
                $table->string('country', 2)->nullable();
                // pending|active|restricted|disabled (free-form, mirrored
                // from each provider's vocabulary).
                $table->string('status', 32)->default('pending');
                // Provider-supplied human-readable reason shown on hover.
                $table->string('status_reason', 500)->nullable();
                // Whether payouts can currently land. Used by the UI badge
                // colour without forcing the consumer to know provider terms.
                $table->boolean('payouts_enabled')->default(false);
                // Whether the creator has finished provider KYC + onboarding.
                $table->boolean('charges_enabled')->default(false);
                // Marks the active default for routing future paid features.
                // The repo enforces "exactly one default per user" via the
                // service layer rather than a partial unique index, since
                // SQLite (used in tests) doesn't accept WHERE on UNIQUE.
                $table->boolean('is_default')->default(false);
                // Flag that this provider is one of the adult-friendly set
                // (CCBill, Segpay) — denormalised so the UI can filter
                // without re-importing the provider registry on every read.
                $table->boolean('adult_friendly')->default(false);
                // Last successful sync from the provider (used to surface a
                // "verified moments ago" signal under each connection card).
                $table->timestamp('last_sync_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_default']);
                $table->unique(['user_id', 'provider']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'adult_content_enabled')) {
                // 18+ opt-in for the creator's profile + paid features.
                // Locked off by default; creators must affirm age + ToS.
                $table->boolean('adult_content_enabled')->default(false);
            }
            if (!Schema::hasColumn('users', 'adult_content_enabled_at')) {
                // First-time opt-in audit timestamp (oldest consent).
                $table->timestamp('adult_content_enabled_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'age_verified_at')) {
                // Most recent age + identity affirmation. Re-stamped
                // on every consent dialog confirm so disputes have a
                // current acknowledgment to point at.
                $table->timestamp('age_verified_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'adult_flag_suspended_at')) {
                // Admin suspension of the adult flag — when set, the
                // creator's profile renders as SFW even though they
                // opted in. The original consent timestamp is kept.
                $table->timestamp('adult_flag_suspended_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'adult_flag_suspended_reason')) {
                $table->string('adult_flag_suspended_reason', 500)->nullable();
            }
            if (!Schema::hasColumn('users', 'adult_flag_suspended_by')) {
                // Admin user id who took the action (nullable so the
                // backfill paths don't have to invent one).
                $table->unsignedBigInteger('adult_flag_suspended_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_payment_connections');
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'adult_content_enabled',
                'adult_content_enabled_at',
                'age_verified_at',
                'adult_flag_suspended_at',
                'adult_flag_suspended_reason',
                'adult_flag_suspended_by',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
