<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #5008 — Event contact exchange.
 *
 * event_discoverability: per (user, event-link) opt-in row.
 *   - expires_at mirrors the event end_at so discovery auto-closes.
 *   - lat/lng snapshot the coords at opt-in time for the radius gate.
 *
 * event_contact_exchanges: directed exchange request between two attendees
 *   at the same event.
 *   - status: pending | accepted | declined
 *   - accepted_at: when both sides are connected; null until then.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_discoverability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('link_id');
            $table->timestamp('expires_at')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'link_id']);
            $table->index(['link_id', 'expires_at']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('link_id')->references('id')->on('links')->cascadeOnDelete();
        });

        Schema::create('event_contact_exchanges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('recipient_id');
            $table->unsignedBigInteger('link_id');
            $table->string('status', 20)->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['requester_id', 'recipient_id', 'link_id']);
            $table->index(['recipient_id', 'status']);
            $table->index(['link_id']);

            $table->foreign('requester_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recipient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('link_id')->references('id')->on('links')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_contact_exchanges');
        Schema::dropIfExists('event_discoverability');
    }
};
