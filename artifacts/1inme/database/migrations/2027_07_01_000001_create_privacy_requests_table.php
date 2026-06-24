<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Privacy data requests (GDPR right-to-erasure / right-to-access, CCPA).
 *
 * One row per visitor/user request to either permanently delete their
 * account or download a full copy of their data. Requests are submitted
 * from a public form, ownership is confirmed (email link for anonymous
 * submitters, session for logged-in users), then a staff member approves
 * or rejects. Approved requests are fulfilled by a queued job after a
 * short grace window (deletion) or immediately (export).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();

            // 'deletion' | 'export'
            $table->string('type', 16)->index();

            // The account this request resolves to, matched by email at
            // submit time. Nullable: the email may not match any account,
            // and a deletion request outlives the account it removes.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // The email the requester typed (lowercased). The verification
            // link and all notifications are sent here.
            $table->string('email', 190)->index();
            $table->text('reason')->nullable();

            // pending_verification -> verified -> approved | rejected
            //                       -> processing -> completed | failed | blocked
            $table->string('status', 24)->default('pending_verification')->index();

            // Ownership verification (anonymous submitters only).
            $table->string('verification_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            // When an approved deletion becomes due (cooling-off window).
            $table->timestamp('scheduled_at')->nullable();

            // Staff decision.
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Fulfillment outcome.
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Secure, expiring download link for export archives.
            $table->string('download_token', 64)->nullable()->unique();
            $table->string('archive_path', 255)->nullable();
            $table->timestamp('download_expires_at')->nullable();

            $table->string('ip', 64)->nullable();

            // Append-only audit trail of lifecycle events.
            $table->jsonb('audit')->nullable();

            $table->timestamps();

            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_requests');
    }
};
