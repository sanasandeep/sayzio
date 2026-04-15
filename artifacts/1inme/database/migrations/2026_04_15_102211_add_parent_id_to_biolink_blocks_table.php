<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biolink_blocks', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('link_id');
            $table->foreign('parent_id')->references('id')->on('biolink_blocks')->onDelete('cascade');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('biolink_blocks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
