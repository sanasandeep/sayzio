<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_growth_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('workspace_id')->nullable();
            $t->string('scope_key', 64);
            $t->uuid('request_token')->unique();
            $t->json('context');
            $t->json('report');
            $t->string('snapshot_hash', 64);
            $t->unsignedInteger('coins_spent')->default(0);
            $t->timestamps();
            $t->index(['user_id', 'workspace_id', 'scope_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_growth_reports');
    }
};
