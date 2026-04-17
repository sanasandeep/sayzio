<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            $table->boolean('all_day')->default(false)->after('end_date');
            $table->string('recurrence_freq', 20)->nullable()->after('all_day');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_freq');
            $table->unsignedSmallInteger('recurrence_count')->nullable()->after('recurrence_interval');
            $table->date('recurrence_until')->nullable()->after('recurrence_count');
            $table->string('recurrence_byday', 100)->nullable()->after('recurrence_until');
            $table->json('extra_schedules')->nullable()->after('recurrence_byday');
        });
    }

    public function down(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            $table->dropColumn([
                'all_day',
                'recurrence_freq',
                'recurrence_interval',
                'recurrence_count',
                'recurrence_until',
                'recurrence_byday',
                'extra_schedules',
            ]);
        });
    }
};
