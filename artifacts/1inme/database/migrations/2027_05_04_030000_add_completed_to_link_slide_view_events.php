<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_slide_view_events', function (Blueprint $table) {
            $table->boolean('completed')->default(false)->after('slide_index');
            $table->index(['deck_id', 'completed']);
        });
    }

    public function down(): void
    {
        Schema::table('link_slide_view_events', function (Blueprint $table) {
            $table->dropIndex(['deck_id', 'completed']);
            $table->dropColumn('completed');
        });
    }
};
