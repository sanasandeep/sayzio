<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('file_links')) {
            Schema::create('file_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->string('original_name');
                $table->string('stored_path');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedBigInteger('download_count')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ics_data')) {
            Schema::create('ics_data', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->string('event_name');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('organizer')->nullable();
                $table->string('organizer_email')->nullable();
                $table->timestamp('start_date');
                $table->timestamp('end_date');
                $table->string('timezone')->default('UTC');
                $table->string('url')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vcf_data')) {
            Schema::create('vcf_data', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('organization')->nullable();
                $table->string('title')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('phone_work')->nullable();
                $table->string('website')->nullable();
                $table->string('street')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('zip')->nullable();
                $table->string('country')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vcf_data');
        Schema::dropIfExists('ics_data');
        Schema::dropIfExists('file_links');
    }
};
