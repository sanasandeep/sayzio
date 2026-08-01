<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6472 — notes can carry an attached website (URL + page title) so the
 * Zio Browser's "note for this website" surface, the web notes page, and the
 * mobile notes tab all share the same attachment. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dialer_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('dialer_notes', 'attached_url')) {
                $table->text('attached_url')->nullable();
            }
            if (!Schema::hasColumn('dialer_notes', 'attached_title')) {
                $table->string('attached_title', 255)->nullable();
            }
            if (!Schema::hasColumn('dialer_notes', 'attached_host')) {
                // Lower-cased host extracted from attached_url, kept in a
                // dedicated column so "notes for this site" can filter by
                // domain without URL parsing in SQL.
                $table->string('attached_host', 255)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('dialer_notes', function (Blueprint $table) {
            foreach (['attached_url', 'attached_title', 'attached_host'] as $col) {
                if (Schema::hasColumn('dialer_notes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
