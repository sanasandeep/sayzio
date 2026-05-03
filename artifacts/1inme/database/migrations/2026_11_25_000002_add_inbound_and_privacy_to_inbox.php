<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // Per-workspace HMAC secret for the signed inbound webhook that
        // accepts social DM payloads (Instagram/TikTok/X) and forwarded
        // emails into Inbox 2.0.
        Schema::table('workspaces', function (Blueprint $t) {
            if (!Schema::hasColumn('workspaces', 'inbox_inbound_token')) {
                $t->string('inbox_inbound_token', 64)->nullable()->unique();
            }
        });

        // Mark threads as private (default for sponsorship). Private threads
        // are only visible to the workspace owner, the assignee, or members
        // who hold inbox.edit.
        Schema::table('inbox_threads', function (Blueprint $t) {
            if (!Schema::hasColumn('inbox_threads', 'is_private')) {
                $t->boolean('is_private')->default(false)->after('status');
            }
        });

        // Backfill: every existing workspace gets a token so the inbound
        // webhook works without a manual rotation step.
        $rows = DB::table('workspaces')->whereNull('inbox_inbound_token')->pluck('id');
        foreach ($rows as $id) {
            DB::table('workspaces')->where('id', $id)->update([
                'inbox_inbound_token' => Str::random(48),
            ]);
        }

        // Sponsorship threads default to private; existing rows get flipped.
        DB::table('inbox_threads')->where('category', 'sponsorship')->update(['is_private' => true]);
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            if (Schema::hasColumn('workspaces', 'inbox_inbound_token')) {
                $t->dropColumn('inbox_inbound_token');
            }
        });
        Schema::table('inbox_threads', function (Blueprint $t) {
            if (Schema::hasColumn('inbox_threads', 'is_private')) {
                $t->dropColumn('is_private');
            }
        });
    }
};
