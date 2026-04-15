<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->unsignedBigInteger('block_id')->nullable()->after('link_id');
            $table->string('block_type', 50)->nullable()->after('block_id');
            $table->string('destination_url', 2048)->nullable()->after('block_type');
            $table->index(['link_id', 'block_id']);
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'block_id']);
            $table->dropColumn(['block_id', 'block_type', 'destination_url']);
        });
    }
};
