<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('poll_vote_reset_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('biolink_blocks')->nullOnDelete();
            $table->json('counts');
            $table->unsignedInteger('total')->default(0);
            $table->timestamp('reset_at');
            $table->timestamp('restored_at')->nullable();
            $table->string('ip_address', 64)->nullable();

            $table->index(['block_id', 'reset_at']);
            $table->index(['creator_id', 'reset_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_vote_reset_snapshots');
    }
};
