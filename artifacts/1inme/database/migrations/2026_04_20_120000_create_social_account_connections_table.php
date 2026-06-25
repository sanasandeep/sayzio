<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('social_account_connections')) {
            Schema::create('social_account_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('platform', 32);                // instagram | tiktok | youtube | twitter | facebook | linkedin | pinterest | twitch | github
                $table->string('handle', 191);                 // @username or channel name
                $table->string('display_name', 191)->nullable();
                $table->string('profile_url', 512)->nullable(); // canonical URL to their profile
                $table->string('avatar_url', 512)->nullable();
                $table->string('external_id', 191)->nullable();// channel id / user id
                $table->text('access_token')->nullable();      // optional OAuth token (encrypted in app layer if needed)
                $table->text('refresh_token')->nullable();
                $table->timestampTz('token_expires_at')->nullable();
                $table->unsignedBigInteger('follower_count')->nullable();
                $table->timestampTz('last_refreshed_at')->nullable();
                $table->string('last_refresh_status', 32)->default('pending'); // pending | ok | error | unsupported
                $table->text('last_refresh_error')->nullable();
                $table->json('meta')->nullable();              // free-form extra data per provider
                $table->timestamps();

                $table->unique(['user_id', 'platform', 'handle']);
                $table->index(['user_id', 'platform']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_account_connections');
    }
};
