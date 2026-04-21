<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_page_id')->constrained('site_pages')->cascadeOnDelete();
            $table->string('slug', 80)->index();
            $table->string('title', 200)->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->text('intro')->nullable();
            $table->date('last_updated_at')->nullable();
            $table->boolean('show_toc')->default(true);
            $table->json('sections')->nullable();
            $table->string('cta_label', 120)->nullable();
            $table->string('cta_url', 500)->nullable();
            $table->string('summary', 500)->nullable();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('editor_name', 190)->nullable();
            $table->timestamps();
            $table->index(['site_page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_revisions');
    }
};
