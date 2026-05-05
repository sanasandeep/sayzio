<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            $table->json('slots')->nullable()->after('extra_schedules');
            $table->string('monthly_mode', 32)->nullable()->after('slots');
            $table->string('monthly_weekday_ordinal', 8)->nullable()->after('monthly_mode');
            $table->unsignedTinyInteger('yearly_month')->nullable()->after('monthly_weekday_ordinal');
        });

        Schema::table('rsvps', function (Blueprint $table) {
            $table->string('status', 16)->default('confirmed')->after('response');
            $table->json('occurrences')->nullable()->after('status');
            $table->json('answers')->nullable()->after('occurrences');
            $table->string('company', 191)->nullable()->after('answers');
            $table->string('role', 191)->nullable()->after('company');
            $table->string('manage_token', 64)->nullable()->unique()->after('role');
            $table->index(['link_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('auto_sync_calendar_account_id')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            $table->dropColumn(['slots', 'monthly_mode', 'monthly_weekday_ordinal', 'yearly_month']);
        });

        Schema::table('rsvps', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'status']);
            $table->dropUnique(['manage_token']);
            $table->dropColumn(['status', 'occurrences', 'answers', 'company', 'role', 'manage_token']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('auto_sync_calendar_account_id');
        });
    }
};
