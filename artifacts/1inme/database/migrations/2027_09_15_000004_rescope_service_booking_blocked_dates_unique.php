<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Blocked dates gained a nullable staff_id (per-staff days off). The original
 * unique (service_booking_id, date) constraint prevented a per-staff block on
 * a date that is also blocked page-wide, and prevented two members blocking
 * the same date. Rescope to partial unique indexes: one for page-level rows
 * (staff_id IS NULL), one per member.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_booking_blocked_dates')) {
            return;
        }

        DB::statement('ALTER TABLE service_booking_blocked_dates DROP CONSTRAINT IF EXISTS service_booking_blocked_dates_service_booking_id_date_unique');
        DB::statement('DROP INDEX IF EXISTS service_booking_blocked_dates_service_booking_id_date_unique');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS sb_blocked_dates_page_unique ON service_booking_blocked_dates (service_booking_id, date) WHERE staff_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS sb_blocked_dates_staff_unique ON service_booking_blocked_dates (service_booking_id, staff_id, date) WHERE staff_id IS NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('service_booking_blocked_dates')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS sb_blocked_dates_page_unique');
        DB::statement('DROP INDEX IF EXISTS sb_blocked_dates_staff_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS service_booking_blocked_dates_service_booking_id_date_unique ON service_booking_blocked_dates (service_booking_id, date)');
    }
};
