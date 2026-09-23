<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sana, 2026-09-23: "here resume items can be also hidden... like hide
 * unhide".
 *
 * A resume accumulates. Seven jobs, four degrees, a dozen projects --
 * and which of them belong on the version you are sending today is not
 * the same question as which of them happened. Until now the only way to
 * leave one off was to delete it, which loses it for every other version
 * too.
 *
 * A column rather than a flag inside `data`, because three separate
 * renderers have to agree on it -- the public page, the PDF and the ATS
 * checker -- and a hidden item must be invisible to all three while
 * staying editable in the builder. That is a query concern, not a
 * payload one.
 *
 * Defaults to false, so every existing item stays exactly as visible as
 * it is today.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resume_section_items')) {
            return;
        }

        if (! Schema::hasColumn('resume_section_items', 'is_hidden')) {
            Schema::table('resume_section_items', function (Blueprint $table) {
                $table->boolean('is_hidden')->default(false)->after('position');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('resume_section_items')
            && Schema::hasColumn('resume_section_items', 'is_hidden')) {
            Schema::table('resume_section_items', function (Blueprint $table) {
                $table->dropColumn('is_hidden');
            });
        }
    }
};
