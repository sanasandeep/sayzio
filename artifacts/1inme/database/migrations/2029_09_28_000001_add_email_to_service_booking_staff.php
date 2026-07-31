<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional notification email for Service Booking staff members (Task #6338).
 * When set, the member is emailed when a booking is placed / rescheduled /
 * cancelled for them and included in appointment reminder emails.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_booking_staff')) {
            return;
        }
        if (!Schema::hasColumn('service_booking_staff', 'email')) {
            Schema::table('service_booking_staff', function (Blueprint $table) {
                $table->string('email', 190)->nullable()->after('bio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_booking_staff') && Schema::hasColumn('service_booking_staff', 'email')) {
            Schema::table('service_booking_staff', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
