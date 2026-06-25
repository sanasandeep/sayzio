<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('resumes')) {
            Schema::create('resumes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('template_id', 40)->default('classic');
                $table->string('color_theme_id', 40)->default('graphite');
                // header / summary / custom_sections live in this JSON blob.
                // List items (experience, education, etc.) live in their own
                // table for ordered CRUD.
                $table->jsonb('sections')->default('{}');
                $table->timestamps();

                $table->index('template_id');
            });
        }

        if (!Schema::hasTable('resume_section_items')) {
            Schema::create('resume_section_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resume_id')->constrained('resumes')->cascadeOnDelete();
                // experience | education | skill | project | certification |
                // award | language | link | custom (custom_section_key in data)
                $table->string('section_type', 40);
                $table->unsignedInteger('position')->default(0);
                $table->jsonb('data')->default('{}');
                $table->timestamps();

                $table->index(['resume_id', 'section_type', 'position'], 'resume_items_section_pos_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resume_section_items');
        Schema::dropIfExists('resumes');
    }
};
