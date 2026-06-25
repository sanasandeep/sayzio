<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contact_imports')) {
            Schema::create('contact_imports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('original_filename', 191)->nullable();
                $table->string('status', 16)->default('pending'); // pending|processing|completed|failed
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('processed_rows')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('skipped_cap_count')->default(0);
                $table->json('failed')->nullable();   // [{row,name,reason}, ...]
                $table->json('rows')->nullable();     // pending parsed rows for the worker
                $table->text('error')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
    }
};
