<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-login audit row used by the suspicious-login alert pipeline.
 *
 * Every successful login (web OTP, email+password, mobile OTP, native
 * social, web social OAuth, demo) inserts one row here. The
 * LoginAlertService compares the new row against the most recent N
 * rows on the same account; if the country, OS family, or browser
 * family is brand new, an email goes out with a "This wasn't me"
 * one-click button keyed to the row's signed `revoke_token`.
 *
 * `personal_access_token_id` is the Sanctum token id (when the login
 * minted one — i.e. mobile/api flows). For the web session login it
 * is null and `session_id` carries the session row identifier so the
 * revoke endpoint can kill the right session and log the user out.
 *
 * `revoked_at` flips when the user clicks "This wasn't me", and is
 * what the recent-logins UI uses to badge the row "Revoked".
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('login_events')) {
            Schema::create('login_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('channel', 32);
                $table->string('ip', 45)->nullable();
                $table->string('country_code', 2)->nullable();
                $table->string('platform', 32)->nullable();
                $table->string('browser', 64)->nullable();
                $table->string('device_label', 120)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('personal_access_token_id')->nullable();
                $table->string('session_id', 191)->nullable();
                $table->boolean('is_new')->default(false);
                $table->json('new_reasons')->nullable();
                $table->boolean('alert_sent')->default(false);
                $table->timestamp('revoked_at')->nullable();
                $table->string('revoke_token', 64)->unique();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->index(['user_id', 'country_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
