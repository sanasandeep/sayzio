<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table) {
            // Associates a `resume` link type with the standalone Resume
            // record it surfaces. Nullable so a resume link can fall back
            // to the owner's default version, and so every other link type
            // leaves it untouched. nullOnDelete keeps the link row alive if
            // the resume version is later removed.
            $table->foreignId('resume_id')
                ->nullable()
                ->after('domain_id')
                ->constrained('resumes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resume_id');
        });
    }
};
