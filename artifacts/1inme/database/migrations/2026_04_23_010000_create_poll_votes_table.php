<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('poll_votes')) {
            Schema::create('poll_votes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->foreignId('block_id')->constrained('biolink_blocks')->cascadeOnDelete();
                $table->unsignedSmallInteger('option_index');
                $table->string('option_label', 191)->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('voter_fingerprint', 64)->nullable();
                $table->string('source', 32)->default('biolink');
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamps();

                $table->index(['link_id', 'block_id']);
                $table->unique(['block_id', 'voter_fingerprint'], 'poll_votes_block_voter_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
