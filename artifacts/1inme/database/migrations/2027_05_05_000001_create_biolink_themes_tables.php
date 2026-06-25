<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('biolink_themes')) {
            Schema::create('biolink_themes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->string('name', 120);
                $table->jsonb('settings')->default('{}');
                $table->timestamps();

                $table->index(['link_id', 'name']);
            });
        }

        if (!Schema::hasTable('biolink_theme_schedules')) {
            Schema::create('biolink_theme_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->foreignId('theme_id')->constrained('biolink_themes')->cascadeOnDelete();
                // Snapshot of the link's biolink settings captured the moment
                // this schedule activates, so we can revert cleanly when it ends
                // (even if the user has re-edited the page in the meantime).
                $table->jsonb('prev_settings')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                $table->string('timezone', 64)->default('UTC');
                // pending → active → completed | cancelled
                $table->string('status', 16)->default('pending');
                $table->timestamp('applied_at')->nullable();
                $table->timestamp('reverted_at')->nullable();
                $table->timestamps();

                $table->index(['link_id', 'status']);
                $table->index(['status', 'starts_at']);
                $table->index(['status', 'ends_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biolink_theme_schedules');
        Schema::dropIfExists('biolink_themes');
    }
};
