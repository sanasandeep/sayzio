<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('backlinks')) {
            Schema::create('backlinks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('page_url', 2048);
                $t->string('page_host', 253)->index();
                $t->string('page_title', 500)->nullable();
                $t->string('anchor_text', 500)->nullable();
                $t->string('matched_url', 2048);
                // 'short_link' | 'biolink_username' | 'custom_domain'
                $t->string('matched_property_type', 32)->index();
                // The slug / handle / host that matched (for grouping in the UI)
                $t->string('matched_property_value', 253)->nullable()->index();
                $t->timestamp('first_seen_at')->useCurrent();
                $t->timestamps();

                // Dedupe: same user, same page, same matched destination only once.
                $t->unique(['user_id', 'page_url', 'matched_url'], 'backlinks_user_page_match_unique');
                $t->index(['user_id', 'first_seen_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('backlinks');
    }
};
