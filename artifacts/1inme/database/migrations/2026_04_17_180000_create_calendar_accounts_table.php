<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calendar_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);                 // google | microsoft | caldav
            $table->string('display_name', 191);            // user-facing label, e.g. "you@gmail.com — Personal"
            $table->string('account_email', 191)->nullable();
            $table->string('external_account_id', 191)->nullable(); // provider's user/account id
            $table->text('access_token')->nullable();       // encrypted JSON for caldav (url/user/pass)
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->string('scope', 1024)->nullable();
            $table->string('default_calendar_id', 191)->nullable(); // which calendar to read/write
            $table->json('settings')->nullable();           // sync window, timezone, push prefs, etc.
            $table->string('sync_token', 1024)->nullable(); // provider incremental cursor
            $table->timestampTz('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->nullable(); // ok | error | running
            $table->text('last_sync_error')->nullable();
            $table->boolean('mirror_enabled')->default(true);   // pull events → create Event Invite links
            $table->boolean('push_enabled')->default(true);     // allow pushing Sayzio Event Invites here
            $table->timestamps();

            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_accounts');
    }
};
