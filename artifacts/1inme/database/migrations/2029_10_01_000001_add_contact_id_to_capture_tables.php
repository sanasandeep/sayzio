<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified contact linking (Task #6501): every customer-capture table gains a
 * nullable contact_id pointing at the owner's Contact, resolved by
 * email/phone match (or auto-created). Additive-only; no FK constraints so
 * linking can never block the customer-facing write path and rows survive
 * contact deletion sweeps (stale ids are treated as unlinked at read time).
 * Contacts themselves gain an is_auto_captured flag distinguishing
 * auto-captured contacts from manually added ones.
 */
return new class extends Migration
{
    private const CAPTURE_TABLES = [
        'subscribers',
        'form_submissions',
        'restaurant_orders',
        'store_orders',
        'service_booking_requests',
        'rsvps',
        'event_tickets',
        'product_orders',
        'reviews',
        'inbox_threads',
    ];

    public function up(): void
    {
        foreach (self::CAPTURE_TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'contact_id')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedBigInteger('contact_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('contacts') && !Schema::hasColumn('contacts', 'is_auto_captured')) {
            Schema::table('contacts', function (Blueprint $t) {
                $t->boolean('is_auto_captured')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (self::CAPTURE_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'contact_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('contact_id');
                });
            }
        }

        if (Schema::hasTable('contacts') && Schema::hasColumn('contacts', 'is_auto_captured')) {
            Schema::table('contacts', function (Blueprint $t) {
                $t->dropColumn('is_auto_captured');
            });
        }
    }
};
