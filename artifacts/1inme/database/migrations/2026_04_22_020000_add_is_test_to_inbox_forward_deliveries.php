<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbox_forward_deliveries', function (Blueprint $t) {
            $t->boolean('is_test')->default(false)->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('inbox_forward_deliveries', function (Blueprint $t) {
            $t->dropColumn('is_test');
        });
    }
};
