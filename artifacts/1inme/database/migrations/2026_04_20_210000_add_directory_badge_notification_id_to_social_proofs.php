<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_proofs', function (Blueprint $table) {
            $table->string('directory_badge_notification_id')->nullable()->after('notifications');
        });
    }

    public function down(): void
    {
        Schema::table('social_proofs', function (Blueprint $table) {
            $table->dropColumn('directory_badge_notification_id');
        });
    }
};
