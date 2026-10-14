<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $t) {
            $t->foreignId('folder_id')->nullable()->constrained('projects')->nullOnDelete();
        });
        Schema::table('vault_attachments', function (Blueprint $t) {
            $t->string('purpose', 20)->default('document');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', fn (Blueprint $t) => $t->dropConstrainedForeignId('folder_id'));
        Schema::table('vault_attachments', fn (Blueprint $t) => $t->dropColumn('purpose'));
    }
};
