<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema additions for outbound webhook triggers on link events.
 *
 * 1. inbox_forward_destinations — adds `click_milestones` (JSON array of
 *    integer thresholds, e.g. [100, 1000, 10000]) for destinations that
 *    want milestone-based webhook events.
 *
 * 2. links — adds `webhook_expired_fired_at` so the expiry-webhook command
 *    stamps each link exactly once; prevents repeated fires after the first.
 *
 * 3. link_click_milestone_fires — idempotency table tracking which
 *    (link × destination × threshold) combinations have already triggered.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inbox_forward_destinations') &&
            !Schema::hasColumn('inbox_forward_destinations', 'click_milestones')) {
            Schema::table('inbox_forward_destinations', function (Blueprint $t) {
                $t->json('click_milestones')->nullable()->after('sources');
            });
        }

        if (Schema::hasTable('links') &&
            !Schema::hasColumn('links', 'webhook_expired_fired_at')) {
            Schema::table('links', function (Blueprint $t) {
                $t->timestamp('webhook_expired_fired_at')->nullable()->after('moderation_appeal_message');
            });
        }

        if (!Schema::hasTable('link_click_milestone_fires')) {
            Schema::create('link_click_milestone_fires', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('link_id');
                $t->foreignId('destination_id')->constrained('inbox_forward_destinations')->cascadeOnDelete();
                $t->unsignedBigInteger('threshold');
                $t->timestamp('fired_at');

                $t->unique(['link_id', 'destination_id', 'threshold'], 'lkmf_unique_fire');
                $t->index(['link_id', 'destination_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_click_milestone_fires');

        if (Schema::hasTable('links') && Schema::hasColumn('links', 'webhook_expired_fired_at')) {
            Schema::table('links', function (Blueprint $t) {
                $t->dropColumn('webhook_expired_fired_at');
            });
        }

        if (Schema::hasTable('inbox_forward_destinations') &&
            Schema::hasColumn('inbox_forward_destinations', 'click_milestones')) {
            Schema::table('inbox_forward_destinations', function (Blueprint $t) {
                $t->dropColumn('click_milestones');
            });
        }
    }
};
