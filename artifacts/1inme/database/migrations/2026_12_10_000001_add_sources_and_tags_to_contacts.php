<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Pages we lifted this contact from (extension save / vCard URL).
            // Append-only — merge() pushes a new entry on every match.
            $table->json('sources')->nullable()->after('notes');
            // Free-form per-user tags (e.g. "lead", "from-extension").
            $table->json('tags')->nullable()->after('sources');
            // Optional website URL on the contact (canonicalized).
            $table->string('website', 500)->nullable()->after('job_title');
            // Optional handles map (e.g. {"twitter":"@x","linkedin":"…"})
            $table->json('socials')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['sources', 'tags', 'website', 'socials']);
        });
    }
};
