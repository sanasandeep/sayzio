<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-user, per-type opt-in/out for in-app and email channels.
        // Rows are created lazily by NotificationService::prefersChannel()
        // — absence of a row means "use the type's default", so we can
        // ship without a backfill.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 64);
            $table->boolean('in_app')->default(true);
            $table->boolean('email')->default(false);
            $table->boolean('push')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'type']);
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Audit + bookkeeping for any admin-composed broadcast. One row
        // per send; the actual user-visible rows live in user_notifications
        // and reference this broadcast via data->>'broadcast_id'.
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            // all | plan | role | country | user
            $table->string('target_kind', 16);
            // plan slug, role name, country code, or user id depending on target_kind
            $table->string('target_value', 120)->nullable();
            $table->string('type', 64)->default('system_broadcast');
            $table->string('subject', 200);
            $table->text('body');
            $table->string('target_url', 500)->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('admin_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_broadcasts');
        Schema::dropIfExists('notification_preferences');
    }
};
