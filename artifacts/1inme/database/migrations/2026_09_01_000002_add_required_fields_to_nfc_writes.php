<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('nfc_writes', function (Blueprint $table) {
            // Spec'd fields the mobile app will populate when it logs a write.
            $table->timestampTz('written_at')->nullable()->after('platform');
            $table->string('device_label', 120)->nullable()->after('device');
            $table->decimal('lat', 10, 7)->nullable()->after('device_label');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->string('source', 24)->default('mobile')->after('lng'); // mobile|web|import|api
            $table->index(['user_id', 'written_at']);
        });

        // Backfill written_at = created_at for any rows already inserted.
        DB::statement('UPDATE nfc_writes SET written_at = created_at WHERE written_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('nfc_writes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'written_at']);
            $table->dropColumn(['written_at', 'device_label', 'lat', 'lng', 'source']);
        });
    }
};
