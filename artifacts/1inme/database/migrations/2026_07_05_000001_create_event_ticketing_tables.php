<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3589 — Public events directory + paid ticketing.
 *
 * Adds ticket tiers + issued tickets for the `ics` event link type, plus
 * additive lat/lng columns on ics_data for the "near me" directory filter.
 * Fully additive/guarded: safe to re-run against a partially-migrated DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_ticket_tiers')) {
            Schema::create('event_ticket_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('price_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->unsignedInteger('capacity')->nullable();
                $table->unsignedInteger('sold_count')->default(0);
                $table->timestamp('sales_start')->nullable();
                $table->timestamp('sales_end')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['link_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('event_tickets')) {
            Schema::create('event_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tier_id')->constrained('event_ticket_tiers')->cascadeOnDelete();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->foreignId('buyer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('attendee_name')->nullable();
                $table->string('attendee_email')->nullable();
                $table->string('attendee_phone')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->unsignedInteger('price_cents')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('code', 64)->unique();
                $table->string('status', 20)->default('valid'); // valid|checked_in|cancelled|refunded
                $table->string('purchase_reference')->nullable();
                $table->string('gateway')->nullable();
                $table->string('gateway_charge_id')->nullable();
                $table->timestamp('checked_in_at')->nullable();
                $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['link_id', 'status']);
                $table->index(['tier_id']);
                $table->index(['buyer_user_id']);
            });
        }

        if (Schema::hasTable('ics_data')) {
            Schema::table('ics_data', function (Blueprint $table) {
                if (!Schema::hasColumn('ics_data', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('location');
                }
                if (!Schema::hasColumn('ics_data', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tickets');
        Schema::dropIfExists('event_ticket_tiers');
        if (Schema::hasTable('ics_data')) {
            Schema::table('ics_data', function (Blueprint $table) {
                if (Schema::hasColumn('ics_data', 'latitude')) $table->dropColumn('latitude');
                if (Schema::hasColumn('ics_data', 'longitude')) $table->dropColumn('longitude');
            });
        }
    }
};
