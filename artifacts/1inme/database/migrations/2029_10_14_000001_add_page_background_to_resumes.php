<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A resume's page background.
 *
 * Every other surface in this rollout stores its background in
 * `links.settings['biolink']`. A resume cannot: it is reachable at
 * `@handle/{slug}` with no Link in scope at all, as well as through a
 * resume link's alias. Hanging the background off the link would make the
 * same resume look different depending on which of its two URLs you
 * opened, which is worse than not having the feature.
 *
 * So it lives on the resume, and both routes render the same page.
 *
 * Nullable with no default: absent means "not chosen", which is what the
 * renderer's opt-in check reads. Every existing resume therefore keeps the
 * light desk it has always had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->json('page_background')->nullable()->after('color_theme_id');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('page_background');
        });
    }
};
