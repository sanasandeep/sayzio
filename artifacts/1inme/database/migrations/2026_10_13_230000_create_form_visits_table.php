<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_visits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $t->string('device', 20)->nullable();
            $t->string('source', 255)->nullable();
            $t->string('campaign', 120)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedInteger('duration_seconds')->nullable();
            $t->unsignedInteger('last_page')->default(1);
            $t->timestamps();
            $t->index(['form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_visits');
    }
};
