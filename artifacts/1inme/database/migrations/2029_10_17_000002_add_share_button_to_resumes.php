<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sana, 2026-09-23: "for all link in bio or any other pages or link
 * types where display is there, i want share button with qr code...
 * customizable options should be there in settings on that link".
 *
 * Every other page type keeps this in its link's settings JSON. A resume
 * cannot: it is reachable at @handle/{slug} with no Link in scope at
 * all, as well as through a resume link's alias. Hanging the setting off
 * the link would give the same resume a share button at one of its URLs
 * and not the other -- the identical reason its page background lives
 * here rather than on the link.
 *
 * Nullable, and read through ShareButton::resolve(), so a resume that
 * has never been configured gets the same defaults as everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resumes')) {
            return;
        }

        if (! Schema::hasColumn('resumes', 'share_button')) {
            Schema::table('resumes', function (Blueprint $table) {
                $table->jsonb('share_button')->nullable()->after('page_background');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('resumes') && Schema::hasColumn('resumes', 'share_button')) {
            Schema::table('resumes', function (Blueprint $table) {
                $table->dropColumn('share_button');
            });
        }
    }
};
