<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-page scoping for AI Mind sources used by the Site Assistant.
 *
 *   page_pattern      — fnmatch-style route name OR URL path the source
 *                       is most relevant to (e.g. `marketing.pricing`,
 *                       `/pricing*`). Null = applies anywhere in its Mind.
 *   assistant_surface — `marketing` / `app` / `any`. Null = no scoping.
 *
 * The Site Assistant retrieval boosts chunks from sources whose
 * page_pattern matches the visitor's current route/path so admin-curated
 * page-specific content wins over generic knowledge.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_mind_sources', function (Blueprint $table) {
            $table->string('page_pattern', 200)->nullable()->after('feature_key');
            $table->string('assistant_surface', 16)->nullable()->after('page_pattern');
            $table->index(['mind_id', 'page_pattern']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_mind_sources', function (Blueprint $table) {
            $table->dropIndex(['mind_id', 'page_pattern']);
            $table->dropColumn(['page_pattern', 'assistant_surface']);
        });
    }
};
