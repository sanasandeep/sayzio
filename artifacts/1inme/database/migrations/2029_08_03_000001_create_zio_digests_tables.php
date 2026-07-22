<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zio Digest (Task #5620): admin-composed rich digests broadcast via
 * email (SendGrid) + WhatsApp, with a public /digest/{slug} page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zio_digests')) {
            Schema::create('zio_digests', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('status', 20)->default('draft'); // draft|published
                $table->string('lead_image', 2048)->nullable();
                $table->text('summary')->nullable();
                $table->json('blocks')->nullable();   // ordered content blocks
                $table->json('audience')->nullable(); // {mode: all|opted_in|plans, plan_ids: []}
                $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();

                // Per-channel send state. idle|queued|sending|sent|failed
                $table->string('email_status', 20)->default('idle');
                $table->string('wa_status', 20)->default('idle');
                $table->unsignedInteger('email_queued_count')->default(0);
                $table->unsignedInteger('email_sent_count')->default(0);
                $table->unsignedInteger('email_failed_count')->default(0);
                $table->unsignedInteger('email_skipped_count')->default(0);
                $table->unsignedInteger('wa_queued_count')->default(0);
                $table->unsignedInteger('wa_sent_count')->default(0);
                $table->unsignedInteger('wa_failed_count')->default(0);
                $table->unsignedInteger('wa_skipped_count')->default(0);
                $table->unsignedInteger('unsubscribed_count')->default(0);

                $table->timestamp('published_at')->nullable();
                $table->timestamp('email_sent_at')->nullable();
                $table->timestamp('wa_sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('zio_digest_recipients')) {
            Schema::create('zio_digest_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digest_id')->constrained('zio_digests')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('channel', 12);   // email|whatsapp
                $table->string('status', 12)->default('queued'); // queued|sent|failed|skipped
                $table->string('error', 500)->nullable();
                $table->timestamps();

                $table->index(['digest_id', 'channel', 'status']);
                $table->unique(['digest_id', 'user_id', 'channel']);
            });
        }

        if (!Schema::hasColumn('users', 'digest_email_opt_out')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('digest_email_opt_out')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zio_digest_recipients');
        Schema::dropIfExists('zio_digests');
        if (Schema::hasColumn('users', 'digest_email_opt_out')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('digest_email_opt_out');
            });
        }
    }
};
