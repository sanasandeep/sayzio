<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('links', function (Blueprint $t) {
            $t->boolean('ar_enabled')->default(false)->after('insurance_fallback_message');
            $t->json('ar_settings')->nullable()->after('ar_enabled');
        });

        Schema::table('page_sessions', function (Blueprint $t) {
            $t->string('source', 32)->nullable()->after('referrer');
            $t->index(['link_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $t) {
            $t->dropColumn(['ar_enabled', 'ar_settings']);
        });
        Schema::table('page_sessions', function (Blueprint $t) {
            $t->dropIndex(['link_id', 'source']);
            $t->dropColumn('source');
        });
    }
};
