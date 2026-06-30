<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Booking link type (Task #3085) — modeled on the Restaurant Menu
 * tables. Request-only appointment booking: a creator publishes bookable
 * services with a weekly availability schedule; visitors pick service(s),
 * choose a genuinely-free upcoming slot and submit a booking request; the
 * owner works the requests through a status workflow. No payment is taken —
 * every price shown is an estimate only.
 *
 * Additive + idempotent (hasTable guards) so it is safe to replay against the
 * shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_bookings')) {
            Schema::create('service_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->unique()->constrained('links')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                // 'display' = show services only; 'booking' = accept requests.
                $table->string('mode', 16)->default('booking');
                $table->string('currency', 3)->default('USD');
                $table->string('accent_color', 16)->default('#3d6bff');
                // Slot granularity (start-time interval), minimum notice, and
                // how far ahead visitors may book.
                $table->unsignedSmallInteger('slot_length_minutes')->default(30);
                $table->unsignedInteger('lead_time_minutes')->default(120);
                $table->unsignedSmallInteger('max_days_ahead')->default(30);
                $table->string('timezone', 64)->default('UTC');
                // tax{enabled,rate,inclusive,label} + free-form instructions.
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_booking_categories')) {
            Schema::create('service_booking_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_booking_id')->constrained('service_bookings')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('service_booking_id');
            });
        }

        if (!Schema::hasTable('service_booking_services')) {
            Schema::create('service_booking_services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_booking_id')->constrained('service_bookings')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('service_booking_categories')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->string('currency', 3)->nullable();
                $table->unsignedSmallInteger('duration_minutes')->default(30);
                $table->text('photo_url')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_unavailable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('service_booking_id');
                $table->index('category_id');
            });
        }

        if (!Schema::hasTable('service_booking_availability_rules')) {
            Schema::create('service_booking_availability_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_booking_id')->constrained('service_bookings')->cascadeOnDelete();
                // 0 = Sunday … 6 = Saturday (matches Carbon::dayOfWeek).
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['service_booking_id', 'day_of_week']);
            });
        }

        if (!Schema::hasTable('service_booking_blocked_dates')) {
            Schema::create('service_booking_blocked_dates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_booking_id')->constrained('service_bookings')->cascadeOnDelete();
                $table->date('date');
                $table->string('reason')->nullable();
                $table->timestamps();
                $table->unique(['service_booking_id', 'date']);
            });
        }

        if (!Schema::hasTable('service_booking_requests')) {
            Schema::create('service_booking_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_booking_id')->constrained('service_bookings')->cascadeOnDelete();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->uuid('public_token')->unique();
                $table->string('status', 16)->default('pending');
                $table->string('customer_name');
                $table->string('customer_email')->nullable();
                $table->string('customer_phone', 40)->nullable();
                $table->text('customer_note')->nullable();
                $table->dateTime('slot_start');
                $table->dateTime('slot_end');
                $table->unsignedSmallInteger('duration_minutes')->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('tax_rate', 6, 3)->default(0);
                $table->boolean('tax_inclusive')->default(false);
                $table->decimal('tax_amount', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['service_booking_id', 'status']);
                $table->index('slot_start');
            });
        }

        if (!Schema::hasTable('service_booking_request_items')) {
            Schema::create('service_booking_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_id')->constrained('service_booking_requests')->cascadeOnDelete();
                $table->foreignId('service_id')->nullable()->constrained('service_booking_services')->nullOnDelete();
                $table->string('name');
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->unsignedSmallInteger('duration_minutes')->default(0);
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('line_total', 10, 2)->default(0);
                $table->timestamps();
                $table->index('request_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_booking_request_items');
        Schema::dropIfExists('service_booking_requests');
        Schema::dropIfExists('service_booking_blocked_dates');
        Schema::dropIfExists('service_booking_availability_rules');
        Schema::dropIfExists('service_booking_services');
        Schema::dropIfExists('service_booking_categories');
        Schema::dropIfExists('service_bookings');
    }
};
