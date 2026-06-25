<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('inbox_replies')) {
            Schema::create('inbox_replies', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('item_type', 32);
                $t->unsignedBigInteger('item_id');
                $t->string('to_email', 200);
                $t->string('from_email', 200)->nullable();
                $t->string('from_name', 200)->nullable();
                $t->string('subject', 300)->nullable();
                $t->text('body');
                $t->string('status', 16)->default('sent');
                $t->text('error')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamps();
                $t->index(['user_id']);
                $t->index(['item_type', 'item_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_replies');
    }
};
