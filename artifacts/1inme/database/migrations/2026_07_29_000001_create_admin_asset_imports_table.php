<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_asset_imports')) {
            Schema::create('admin_asset_imports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admin_id')->nullable();
                $table->string('status', 20)->default('pending'); // pending|downloading|processing|completed|failed
                $table->string('source_type', 10)->default('upload'); // upload|url
                $table->text('source')->nullable();          // original filename or URL/S3 location
                $table->string('mode', 10)->default('skip'); // skip|overwrite duplicates
                $table->string('zip_path')->nullable();      // local temp path of the archive
                $table->unsignedBigInteger('zip_size_bytes')->default(0);
                $table->unsignedInteger('total_entries')->default(0);
                $table->unsignedInteger('processed_entries')->default(0);
                $table->unsignedInteger('imported_count')->default(0);
                $table->unsignedInteger('overwritten_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->json('skipped')->nullable();         // capped [{path, reason}]
                $table->text('error')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_asset_imports');
    }
};
