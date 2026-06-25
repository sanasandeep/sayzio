<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('link_aliases')) {
            Schema::create('link_aliases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->string('alias')->unique();
                $table->timestamps();

                $table->index(['link_id']);
            });
        }

        // Track which alias was used for each click so analytics can break down per-alias.
        Schema::table('link_clicks', function (Blueprint $table) {
            if (!Schema::hasColumn('link_clicks', 'alias')) {
                $table->string('alias')->nullable()->after('link_id');
                $table->index(['link_id', 'alias']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'alias']);
            $table->dropColumn('alias');
        });
        Schema::dropIfExists('link_aliases');
    }
};
