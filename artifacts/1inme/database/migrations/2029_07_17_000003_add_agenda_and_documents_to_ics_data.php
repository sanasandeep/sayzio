<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            $table->jsonb('agenda')->nullable()->after('info_sections');
            $table->jsonb('documents')->nullable()->after('agenda');
        });
    }

    public function down(): void
    {
        Schema::table('ics_data', function (Blueprint $table) {
            if (Schema::hasColumn('ics_data', 'agenda'))    $table->dropColumn('agenda');
            if (Schema::hasColumn('ics_data', 'documents')) $table->dropColumn('documents');
        });
    }
};
