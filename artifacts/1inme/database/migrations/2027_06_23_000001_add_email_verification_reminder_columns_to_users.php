<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_verification_reminders_sent')) {
                $table->unsignedSmallInteger('email_verification_reminders_sent')->default(0);
            }
            if (!Schema::hasColumn('users', 'email_verification_reminder_sent_at')) {
                $table->timestamp('email_verification_reminder_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email_verification_reminders_sent')) {
                $table->dropColumn('email_verification_reminders_sent');
            }
            if (Schema::hasColumn('users', 'email_verification_reminder_sent_at')) {
                $table->dropColumn('email_verification_reminder_sent_at');
            }
        });
    }
};
