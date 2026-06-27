<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity log of every outbound email the platform sends.
 *
 * Written by the central email pipeline (App\Modules\Common\Services\Emailer)
 * for templated/transactional sends, and by the catch-all MessageSent listener
 * (App\Listeners\LogOutboundEmail) for everything else (dynamic composes,
 * system alerts), so coverage is complete. Each row stores enough of the
 * rendered message (subject + body + format + recipient) to power the admin
 * "Email Log" screen and a per-row Resend that re-sends the exact content that
 * went out.
 *
 * Additive, shared-DB-safe: brand-new table guarded by hasTable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id();

                // Registry key (e.g. "auth.verify_email") + its category
                // ("auth"), denormalised so the log screen can filter/group
                // without consulting the registry. "uncategorized" for sends
                // that didn't carry a key (caught by the listener).
                $table->string('email_key', 191)->default('uncategorized');
                $table->string('category', 64)->default('uncategorized');

                $table->string('recipient', 191);
                $table->string('subject', 255)->nullable();

                // The rendered body actually sent, so Resend reproduces it
                // exactly. Can be large (full HTML emails) -> longText.
                $table->longText('body')->nullable();
                $table->string('format', 8)->default('html'); // html | text

                // sent | failed | skipped
                $table->string('status', 16)->default('sent');
                $table->text('error')->nullable();

                // Who/what this email was about, for scoping the user-facing
                // resend and linking back to the related entity in the UI.
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('related_type', 191)->nullable();
                $table->string('related_id', 64)->nullable();

                // cc / bcc / from / reply_to / resent_from / attachments meta.
                $table->json('meta')->nullable();

                $table->timestamps();

                $table->index('email_key', 'email_logs_key_idx');
                $table->index('category', 'email_logs_category_idx');
                $table->index('status', 'email_logs_status_idx');
                $table->index('recipient', 'email_logs_recipient_idx');
                $table->index('user_id', 'email_logs_user_idx');
                $table->index('created_at', 'email_logs_created_idx');
                $table->index(['related_type', 'related_id'], 'email_logs_related_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
