<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per email-verification reminder actually sent. The users table
 * only keeps each user's *most recent* reminder timestamp, which makes the
 * admin weekly trend a proxy ("users whose latest reminder fell this week")
 * rather than a true per-send count. This log lets the trend report exact
 * reminders-sent figures across weeks.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('email_verification_reminder_sends')) {
            return;
        }

        if (!Schema::hasTable('email_verification_reminder_sends')) {
            Schema::create('email_verification_reminder_sends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->timestamp('sent_at');
                $table->timestamps();

                $table->index('user_id');
                $table->index('sent_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_reminder_sends');
    }
};
