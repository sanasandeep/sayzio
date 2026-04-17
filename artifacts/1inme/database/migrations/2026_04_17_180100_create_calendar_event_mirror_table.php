<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_event_mirror', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_account_id')->constrained('calendar_accounts')->cascadeOnDelete();
            $table->foreignId('link_id')->constrained()->cascadeOnDelete();   // the mirrored Event Invite link
            $table->string('external_calendar_id', 191)->nullable();
            $table->string('external_event_id', 191);
            $table->string('etag', 191)->nullable();
            $table->string('ical_uid', 191)->nullable();
            $table->string('source', 16)->default('pull'); // pull | push (where it originated)
            $table->boolean('detached')->default(false);   // user manually detached → stop overwriting
            $table->timestampTz('external_updated_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['calendar_account_id', 'external_event_id'], 'cem_account_event_unique');
            $table->index('link_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_mirror');
    }
};
