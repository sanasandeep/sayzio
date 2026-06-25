<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail + owner alerts for sensitive workspace actions
 * (link deletion, custom-domain changes, follower exports, member removal,
 * API-key rotation, etc).
 *
 * - workspace_audit_events: append-only ledger with a hash chain so any
 *   tampering with prior rows breaks the chain and is detectable.
 * - workspace_audit_alert_prefs: per-(workspace, action) toggle for whether
 *   the workspace owner gets emailed when the action fires. Missing rows
 *   fall back to the catalogue's default in SensitiveActionLogger.
 * - workspace_audit_reports: "this wasn't me" reports filed by an owner
 *   from the alert email, kicking off the investigation flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workspace_audit_events')) {
            Schema::create('workspace_audit_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('action', 64);
                $table->string('target_type', 64)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->string('target_label', 255)->nullable();
                $table->string('ip', 64)->nullable();
                // Free-form context (e.g. CSV row count, domain hostname,
                // departing member email, etc) for investigation purposes.
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at');
                // Hash-chain columns. `prev_hash` is the previous row's `hash`
                // for the same workspace; `hash` is sha256 over the canonical
                // concatenation of (prev_hash | id-fields | payload). Any
                // tampering with an earlier row breaks the chain forward.
                $table->char('prev_hash', 64)->nullable();
                $table->char('hash', 64);
                // "Owner flagged this as unauthorised" markers — populated when
                // a report is filed; do NOT mutate the hash columns.
                $table->timestamp('reported_unauthorized_at')->nullable();
                $table->unsignedBigInteger('reported_by_user_id')->nullable();

                $table->index(['workspace_id', 'occurred_at']);
                $table->index(['workspace_id', 'action']);
                $table->index(['workspace_id', 'actor_user_id']);
            });
        }

        if (!Schema::hasTable('workspace_audit_alert_prefs')) {
            Schema::create('workspace_audit_alert_prefs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('workspace_id');
                $table->string('action', 64);
                $table->boolean('alert_enabled')->default(true);
                $table->timestamps();

                $table->unique(['workspace_id', 'action'], 'wap_workspace_action_unique');
            });
        }

        if (!Schema::hasTable('workspace_audit_reports')) {
            Schema::create('workspace_audit_reports', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('workspace_audit_event_id');
                $table->unsignedBigInteger('reporter_user_id')->nullable();
                $table->string('reporter_email', 191)->nullable();
                $table->string('ip', 64)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('workspace_audit_event_id', 'wareports_event_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_audit_reports');
        Schema::dropIfExists('workspace_audit_alert_prefs');
        Schema::dropIfExists('workspace_audit_events');
    }
};
