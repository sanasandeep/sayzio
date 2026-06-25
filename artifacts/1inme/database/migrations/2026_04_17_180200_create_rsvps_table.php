<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rsvps')) {
            Schema::create('rsvps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete(); // Event Invite link
                $table->string('name', 191);
                $table->string('email', 191)->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('response', 16);  // yes | no | maybe
                $table->unsignedSmallInteger('plus_ones')->default(0);
                $table->text('message')->nullable();
                $table->string('source', 32)->default('event_page'); // event_page | biolink | api
                $table->foreignId('source_block_id')->nullable()->constrained('biolink_blocks')->nullOnDelete();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamps();

                $table->index(['link_id', 'response']);
                $table->index('email');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rsvps');
    }
};
