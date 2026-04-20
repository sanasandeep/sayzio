<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbox_forward_destinations', function (Blueprint $t) {
            $t->timestamp('last_failure_email_sent_at')->nullable()->after('last_status');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_forward_destinations', function (Blueprint $t) {
            $t->dropColumn('last_failure_email_sent_at');
        });
    }
};
