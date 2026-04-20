<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('social_account_connections', function (Blueprint $table) {
            $table->timestampTz('last_broken_email_sent_at')->nullable()->after('last_failure_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('social_account_connections', function (Blueprint $table) {
            $table->dropColumn('last_broken_email_sent_at');
        });
    }
};
