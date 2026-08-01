<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folders for the Sayzio Files vault (Zio Browser "My Files" pane).
 * A flat, single-level folder list per user; user_files gain a nullable
 * folder_id (null = root). Deleting a folder moves its files back to root
 * (nullOnDelete) — file rows are never destroyed by folder deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_file_folders')) {
            Schema::create('user_file_folders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->timestamps();

                $table->unique(['user_id', 'name']);
            });
        }

        if (!Schema::hasColumn('user_files', 'folder_id')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->foreignId('folder_id')->nullable()
                    ->constrained('user_file_folders')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_files', 'folder_id')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->dropConstrainedForeignId('folder_id');
            });
        }
        Schema::dropIfExists('user_file_folders');
    }
};
