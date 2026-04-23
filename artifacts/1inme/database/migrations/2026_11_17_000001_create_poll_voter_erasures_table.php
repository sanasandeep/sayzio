<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('poll_voter_erasures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('biolink_blocks')->nullOnDelete();
            $table->string('identifier', 255);
            $table->unsignedInteger('removed_count')->default(0);
            $table->string('ip_address', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['creator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_voter_erasures');
    }
};
