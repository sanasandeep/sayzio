<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->boolean('is_bot')->default(false)->after('user_agent');
            $table->index(['link_id', 'is_bot']);
        });
    }

    public function down(): void
    {
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropIndex(['link_id', 'is_bot']);
            $table->dropColumn('is_bot');
        });
    }
};
