<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event Connect QR (Task #6685) — one row per (event link, user) who
 * completed the scan-to-connect flow. Attribution store behind the
 * "QR Connect" stats panel: whether the account was newly created by
 * the flow, whether an RSVP was recorded, and whether a follow of the
 * host was created (vs. already existing).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_qr_connects')) return;
        Schema::create('event_qr_connects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('link_id')->index();
            $table->unsignedBigInteger('user_id');
            $table->boolean('was_new_user')->default(false);
            $table->unsignedBigInteger('rsvp_id')->nullable();
            $table->boolean('followed')->default(false);
            $table->timestamps();
            $table->unique(['link_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_qr_connects');
    }
};
