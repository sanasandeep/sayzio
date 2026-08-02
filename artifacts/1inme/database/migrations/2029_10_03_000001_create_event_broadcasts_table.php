<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organizer → guest broadcast log. Every "Message guests" send records a
 * row so the organizer can review what went out (subject, audience, count,
 * when). Additive only, no FK constraints — the link/user may be removed
 * later and the log must survive as a historical record.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_broadcasts')) {
            return;
        }
        Schema::create('event_broadcasts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('link_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            // Audience filter used to resolve recipients:
            // going | waitlist | all_rsvps | ticket_holders
            $t->string('audience', 32);
            $t->string('subject', 255);
            $t->text('message');
            $t->unsignedInteger('recipients_count')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_broadcasts');
    }
};
