<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-file support for card / brochure scans.
 *
 * A scan can now carry several uploads (e.g. front + back of a card,
 * multi-page brochure photos) plus AI-derived assets (rasterised PDF
 * pages, cropped logo). Each entry is a UserFile id, so the originals
 * + derivations all live in the user's vault and are reachable from
 * the review screen, audit log and biolink draft.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('card_scans', function (Blueprint $table) {
            // List of UserFile ids the user actually uploaded (the
            // first one mirrors source_file_id for back-compat).
            $table->json('source_file_ids')->nullable()->after('source_file_id');
            // List of UserFile ids we derived from the upload (PDF
            // pages rendered as PNGs, cropped logo, etc).
            $table->json('derived_file_ids')->nullable()->after('source_file_ids');
        });
    }

    public function down(): void
    {
        Schema::table('card_scans', function (Blueprint $table) {
            $table->dropColumn(['source_file_ids', 'derived_file_ids']);
        });
    }
};
