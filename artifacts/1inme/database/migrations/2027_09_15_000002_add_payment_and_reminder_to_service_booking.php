<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds paid-booking and appointment-reminder columns to the Service Booking
 * tables. Additive + idempotent (hasColumn guards) so it is safe to replay
 * against the shared RDS.
 *
 * service_booking_services — per-service payment mode:
 *   payment_mode    : none | deposit | full  (default none = existing behaviour)
 *   deposit_type    : fixed | percent        (only relevant when mode=deposit)
 *   deposit_value   : the fixed amount or %  (only relevant when mode=deposit)
 *
 * service_booking_requests — payment state + slot-hold expiry:
 *   payment_mode        : none | deposit | full   (snapshot at booking time)
 *   payment_status      : none | pending | paid | refunded
 *   payment_amount_cents: gross amount charged in cents
 *   payment_currency    : ISO 4217 (3-char)
 *   payment_gateway     : adapter key (stripe, paypal, …)
 *   payment_charge_id   : gateway charge / transaction ID
 *   checkout_expires_at : slot hold expiry — awaiting_payment rows block slots
 *                         only while this is in the future
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── service_booking_services ─────────────────────────────
        Schema::table('service_booking_services', function (Blueprint $table) {
            if (!Schema::hasColumn('service_booking_services', 'payment_mode')) {
                $table->string('payment_mode', 16)->default('none')->after('is_active');
            }
            if (!Schema::hasColumn('service_booking_services', 'deposit_type')) {
                $table->string('deposit_type', 16)->nullable()->after('payment_mode');
            }
            if (!Schema::hasColumn('service_booking_services', 'deposit_value')) {
                $table->decimal('deposit_value', 10, 2)->nullable()->after('deposit_type');
            }
        });

        // ── service_booking_requests ─────────────────────────────
        Schema::table('service_booking_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('service_booking_requests', 'payment_mode')) {
                $table->string('payment_mode', 16)->default('none')->after('currency');
            }
            if (!Schema::hasColumn('service_booking_requests', 'payment_status')) {
                $table->string('payment_status', 16)->default('none')->after('payment_mode');
            }
            if (!Schema::hasColumn('service_booking_requests', 'payment_amount_cents')) {
                $table->unsignedInteger('payment_amount_cents')->default(0)->after('payment_status');
            }
            if (!Schema::hasColumn('service_booking_requests', 'payment_currency')) {
                $table->string('payment_currency', 3)->nullable()->after('payment_amount_cents');
            }
            if (!Schema::hasColumn('service_booking_requests', 'payment_gateway')) {
                $table->string('payment_gateway', 32)->nullable()->after('payment_currency');
            }
            if (!Schema::hasColumn('service_booking_requests', 'payment_charge_id')) {
                $table->string('payment_charge_id', 191)->nullable()->after('payment_gateway');
            }
            if (!Schema::hasColumn('service_booking_requests', 'checkout_expires_at')) {
                $table->timestamp('checkout_expires_at')->nullable()->after('payment_charge_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_booking_services', function (Blueprint $table) {
            foreach (['payment_mode', 'deposit_type', 'deposit_value'] as $col) {
                if (Schema::hasColumn('service_booking_services', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('service_booking_requests', function (Blueprint $table) {
            foreach (['payment_mode', 'payment_status', 'payment_amount_cents', 'payment_currency', 'payment_gateway', 'payment_charge_id', 'checkout_expires_at'] as $col) {
                if (Schema::hasColumn('service_booking_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
