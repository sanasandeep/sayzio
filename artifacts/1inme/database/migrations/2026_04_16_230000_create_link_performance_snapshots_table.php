<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('link_performance_snapshots')) {
            Schema::create('link_performance_snapshots', function (Blueprint $t) {
                $t->id();
                $t->foreignId('link_id')->constrained()->cascadeOnDelete();
                $t->date('date');
                $t->unsignedSmallInteger('score');
                $t->json('components_json')->nullable();
                $t->timestamps();

                $t->unique(['link_id', 'date']);
                $t->index(['link_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_performance_snapshots');
    }
};
