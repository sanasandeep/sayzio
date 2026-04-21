<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visibility tiers + demo-content flags.
 *
 * - Adds visibility column to links and feed_events. Default 'public' so
 *   existing rows behave identically to before this migration.
 * - Adds is_demo flag to users, links, and feed_events so the admin
 *   "remove all demo content" button can purge seed rows reliably,
 *   independently of the legacy `alias LIKE 'demo-%'` heuristic.
 *
 * Visibility values: public | registered | followers | subscribers
 *   - public      — anyone, no auth needed (current default)
 *   - registered  — any logged-in viewer or dashboard user
 *   - followers   — only followers of the creator
 *   - subscribers — only paid/email subscribers of the creator
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index();
            }
        });

        Schema::table('links', function (Blueprint $table) {
            if (! Schema::hasColumn('links', 'visibility')) {
                $table->string('visibility', 20)->default('public');
            }
            if (! Schema::hasColumn('links', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index();
            }
        });

        Schema::table('feed_events', function (Blueprint $table) {
            if (! Schema::hasColumn('feed_events', 'visibility')) {
                $table->string('visibility', 20)->default('public');
            }
            if (! Schema::hasColumn('feed_events', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_demo')) $table->dropColumn('is_demo');
        });
        Schema::table('links', function (Blueprint $table) {
            foreach (['visibility', 'is_demo'] as $c) {
                if (Schema::hasColumn('links', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('feed_events', function (Blueprint $table) {
            foreach (['visibility', 'is_demo'] as $c) {
                if (Schema::hasColumn('feed_events', $c)) $table->dropColumn($c);
            }
        });
    }
};
