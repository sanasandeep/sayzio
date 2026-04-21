<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_page_revisions', function (Blueprint $table) {
            // Editors can be either admin accounts or end users, so we drop
            // the strict FK to `users` and add an editor_type discriminator.
            try {
                $table->dropForeign(['editor_id']);
            } catch (\Throwable $e) {
                // FK may not exist on fresh schemas — ignore.
            }
            $table->string('editor_type', 30)->nullable()->after('editor_id');
        });
    }

    public function down(): void
    {
        Schema::table('site_page_revisions', function (Blueprint $table) {
            $table->dropColumn('editor_type');
            $table->foreign('editor_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
