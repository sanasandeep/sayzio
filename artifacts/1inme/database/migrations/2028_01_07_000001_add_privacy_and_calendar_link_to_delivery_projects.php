<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3584 — give each Delivery Project its own calendar.
 *
 * Bridges `calendars` to `delivery_projects` (nullable + unique, mirroring the
 * existing `link_id` bridge) and adds a `privacy` tier (project/workspace/
 * public). Delivery-project calendars have no owning `Link`, so `link_id`
 * must become nullable — done via a raw ALTER because doctrine/dbal (needed
 * by Blueprint::change()) isn't vendored in this project. Postgres allows
 * multiple NULLs in a unique index, so relaxing NOT NULL is safe.
 *
 * Additive + guarded: shared RDS, every step checks current state first.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('calendars')) {
            Schema::table('calendars', function (Blueprint $t) {
                if (!Schema::hasColumn('calendars', 'workspace_id')) {
                    $t->unsignedBigInteger('workspace_id')->nullable()->index()->after('user_id');
                }
                if (!Schema::hasColumn('calendars', 'delivery_project_id')) {
                    $t->unsignedBigInteger('delivery_project_id')->nullable()->unique()->after('workspace_id');
                }
                if (!Schema::hasColumn('calendars', 'privacy')) {
                    // project|workspace|public — only meaningful for delivery-project
                    // calendars; standalone followable calendars leave this null and
                    // keep using `is_public` as before.
                    $t->string('privacy', 20)->nullable()->after('is_public');
                }
            });

            $nullable = DB::selectOne(
                "select is_nullable from information_schema.columns where table_name = 'calendars' and column_name = 'link_id'"
            );
            if ($nullable && strtoupper($nullable->is_nullable) !== 'YES') {
                DB::statement('ALTER TABLE calendars ALTER COLUMN link_id DROP NOT NULL');
            }
        }

        if (Schema::hasTable('delivery_project_tasks') && !Schema::hasColumn('delivery_project_tasks', 'calendar_event_id')) {
            Schema::table('delivery_project_tasks', function (Blueprint $t) {
                $t->unsignedBigInteger('calendar_event_id')->nullable()->index()->after('due_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_project_tasks') && Schema::hasColumn('delivery_project_tasks', 'calendar_event_id')) {
            Schema::table('delivery_project_tasks', function (Blueprint $t) {
                $t->dropColumn('calendar_event_id');
            });
        }

        if (Schema::hasTable('calendars')) {
            Schema::table('calendars', function (Blueprint $t) {
                if (Schema::hasColumn('calendars', 'privacy')) {
                    $t->dropColumn('privacy');
                }
                if (Schema::hasColumn('calendars', 'delivery_project_id')) {
                    $t->dropColumn('delivery_project_id');
                }
                if (Schema::hasColumn('calendars', 'workspace_id')) {
                    $t->dropColumn('workspace_id');
                }
            });
        }
    }
};
