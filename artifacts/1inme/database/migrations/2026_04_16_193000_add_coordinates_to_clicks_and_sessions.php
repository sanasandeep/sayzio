<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('link_clicks', function (Blueprint $t) {
            $t->decimal('latitude', 9, 5)->nullable()->after('city');
            $t->decimal('longitude', 9, 5)->nullable()->after('latitude');
            $t->index(['link_id', 'latitude', 'longitude'], 'link_clicks_link_coords_idx');
        });

        Schema::table('page_sessions', function (Blueprint $t) {
            $t->decimal('latitude', 9, 5)->nullable()->after('city');
            $t->decimal('longitude', 9, 5)->nullable()->after('latitude');
            $t->index(['link_id', 'latitude', 'longitude'], 'page_sessions_link_coords_idx');
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $t) {
            $t->dropIndex('link_clicks_link_coords_idx');
            $t->dropColumn(['latitude', 'longitude']);
        });
        Schema::table('page_sessions', function (Blueprint $t) {
            $t->dropIndex('page_sessions_link_coords_idx');
            $t->dropColumn(['latitude', 'longitude']);
        });
    }
};
