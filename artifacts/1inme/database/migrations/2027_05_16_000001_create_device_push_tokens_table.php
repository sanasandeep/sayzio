<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of Expo push tokens per user/device (task #1403).
 *
 * The 1inme-mobile app registers the Expo push token it gets from
 * expo-notifications after sign-in; the backend fans push notifications
 * (starting with `api.usage_warning`) out to every live token a user
 * owns. Tokens are unique platform-wide — Expo issues one per install —
 * so the same token can migrate between users if a device is handed over,
 * hence the upsert keyed on `token`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('device_push_tokens')) {
            Schema::create('device_push_tokens', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                // Expo push token, e.g. ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx].
                $t->string('token')->unique();
                $t->string('platform', 16)->nullable(); // ios | android | web
                $t->string('device_name')->nullable();
                $t->timestamp('last_used_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_push_tokens');
    }
};
