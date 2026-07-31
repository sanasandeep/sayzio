<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Booking upgrades (Task #6325):
 *   - staff / team members (per-staff services, weekly hours, blocked dates);
 *   - buffer minutes before/after appointments (global + per-service override);
 *   - group capacity per service slot;
 *   - staff assignment + buffer snapshots on booking requests.
 *
 * Additive + idempotent (hasTable / hasColumn guards) so it is safe to replay
 * against the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_booking_staff')) {
            Schema::create('service_booking_staff', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_booking_id')->constrained('service_bookings')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('title', 160)->nullable();
                $table->text('bio')->nullable();
                $table->text('photo_url')->nullable();
                $table->foreignId('calendar_account_id')->nullable()->constrained('calendar_accounts')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->index('service_booking_id');
            });
        }

        if (!Schema::hasTable('service_booking_staff_service')) {
            Schema::create('service_booking_staff_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('service_booking_staff')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('service_booking_services')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['staff_id', 'service_id']);
            });
        }

        if (Schema::hasTable('service_booking_availability_rules')
            && !Schema::hasColumn('service_booking_availability_rules', 'staff_id')) {
            Schema::table('service_booking_availability_rules', function (Blueprint $table) {
                // NULL = page-level rule; set = staff-specific weekly hours.
                $table->foreignId('staff_id')->nullable()->after('service_booking_id')
                    ->constrained('service_booking_staff')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('service_booking_blocked_dates')
            && !Schema::hasColumn('service_booking_blocked_dates', 'staff_id')) {
            Schema::table('service_booking_blocked_dates', function (Blueprint $table) {
                // NULL = page-level block; set = staff-specific day off.
                $table->foreignId('staff_id')->nullable()->after('service_booking_id')
                    ->constrained('service_booking_staff')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('service_booking_services')) {
            Schema::table('service_booking_services', function (Blueprint $table) {
                if (!Schema::hasColumn('service_booking_services', 'capacity')) {
                    // How many concurrent bookings a single slot can hold.
                    $table->unsignedSmallInteger('capacity')->default(1);
                }
                if (!Schema::hasColumn('service_booking_services', 'buffer_before_minutes')) {
                    // NULL = inherit page-level buffer.
                    $table->unsignedSmallInteger('buffer_before_minutes')->nullable();
                }
                if (!Schema::hasColumn('service_booking_services', 'buffer_after_minutes')) {
                    $table->unsignedSmallInteger('buffer_after_minutes')->nullable();
                }
            });
        }

        if (Schema::hasTable('service_booking_requests')) {
            Schema::table('service_booking_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('service_booking_requests', 'staff_id')) {
                    $table->foreignId('staff_id')->nullable()->after('link_id')
                        ->constrained('service_booking_staff')->nullOnDelete();
                }
                if (!Schema::hasColumn('service_booking_requests', 'buffer_before_minutes')) {
                    // Snapshot of the effective buffers at placement time so
                    // availability math never changes retroactively.
                    $table->unsignedSmallInteger('buffer_before_minutes')->default(0);
                }
                if (!Schema::hasColumn('service_booking_requests', 'buffer_after_minutes')) {
                    $table->unsignedSmallInteger('buffer_after_minutes')->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        // Additive-only migration against the shared RDS — no destructive down.
    }
};
