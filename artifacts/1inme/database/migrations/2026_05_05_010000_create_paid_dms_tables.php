<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #1210 — Paid DMs + per-attachment locked media.
 *
 * Builds on the existing biolink-scoped DM tables:
 *   - viewer_dm_conversations gets a nullable link_id + a denormalised
 *     creator_user_id + a `source` enum so profile DMs (which never
 *     belong to a biolink) and biolink DMs share one inbox.
 *   - viewer_dm_messages picks up a `kind` (text|attachment|tip|system)
 *     and FKs to attachment + tip rows so the renderer can branch.
 *   - new viewer_dm_attachments (per-message media with optional unlock
 *     price) and viewer_dm_attachment_unlocks (one row per fan unlock).
 *   - new dm_broadcasts (mass-message campaigns) and dm_welcome_rules
 *     (auto-DM on follow / new subscriber).
 *   - users gets DM access settings (open|subs|paid|closed + price) and
 *     a read-receipts toggle.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── Conversations: allow profile-scoped (no link_id) and add denormalised creator_user_id ──
        Schema::table('viewer_dm_conversations', function (Blueprint $table) {
            // We'll drop the legacy unique index first so we can make
            // link_id nullable and add a new uniqueness rule that
            // covers both biolink and profile threads.
            $table->dropUnique('viewer_dm_unique_pair');
        });

        Schema::table('viewer_dm_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('link_id')->nullable()->change();
            $table->string('source', 16)->default('biolink')->after('link_id'); // biolink|profile
            $table->unsignedBigInteger('creator_user_id')->nullable()->after('source');
            $table->boolean('paid_to_message')->default(false)->after('owner_replied');
            $table->unsignedInteger('paid_amount_cents')->default(0)->after('paid_to_message');
            $table->string('paid_currency', 8)->default('USD')->after('paid_amount_cents');
            $table->timestamp('paid_at')->nullable()->after('paid_currency');
            $table->timestamp('viewer_last_read_at')->nullable()->after('viewer_unread_count');
            $table->timestamp('owner_last_read_at')->nullable()->after('viewer_last_read_at');

            $table->index(['creator_user_id', 'status', 'last_message_at'], 'viewer_dm_creator_idx');
        });

        // Backfill creator_user_id from owner_user_id so the new column
        // is immediately usable. owner_user_id stays as the workspace
        // owner; creator_user_id is the human creator (same row today,
        // but they'll diverge when team members handle DMs on behalf
        // of the creator account).
        DB::table('viewer_dm_conversations')->update([
            'creator_user_id' => DB::raw('owner_user_id'),
        ]);

        // Restore DB-level uniqueness with two Postgres partial unique
        // indexes — one for biolink threads, one for profile threads —
        // so concurrent inserts cannot create duplicate conversations
        // even if the application-level firstOrCreate races.
        //
        //   - viewer_dm_link_pair_uniq:
        //       UNIQUE (link_id, viewer_user_id) WHERE link_id IS NOT NULL
        //     replaces the legacy non-partial viewer_dm_unique_pair so
        //     biolink DMs keep their original guarantee.
        //
        //   - viewer_dm_profile_pair_uniq:
        //       UNIQUE (creator_user_id, viewer_user_id) WHERE source = 'profile'
        //     covers the new profile-scoped threads where link_id is
        //     null and the creator/viewer pair must be globally unique.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX viewer_dm_link_pair_uniq
                ON viewer_dm_conversations (link_id, viewer_user_id)
                WHERE link_id IS NOT NULL
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX viewer_dm_profile_pair_uniq
                ON viewer_dm_conversations (creator_user_id, viewer_user_id)
                WHERE source = 'profile'
        SQL);

        // ── Messages: kind + attachment/tip FKs ───────────────────────
        Schema::table('viewer_dm_messages', function (Blueprint $table) {
            // text: a normal text body
            // attachment: text + 1+ attachments (rows in viewer_dm_attachments)
            // tip: a system-rendered tip card (tip_id set, body holds the note)
            // system: pay-to-message paid receipt, paywall unlocked notice, etc.
            $table->string('kind', 16)->default('text')->after('body');
            $table->unsignedBigInteger('tip_id')->nullable()->after('kind');
            $table->boolean('has_attachments')->default(false)->after('tip_id');
            $table->index(['conversation_id', 'kind'], 'viewer_dm_msg_kind_idx');
        });

        // ── Per-message attachments with optional unlock price ────────
        Schema::create('viewer_dm_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('viewer_dm_messages')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('viewer_dm_conversations')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16); // image|gallery|video|audio|voice|file
            $table->string('url', 1024);
            $table->string('thumb_url', 1024)->nullable();
            $table->string('blur_url', 1024)->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('lock_price_cents')->default(0);
            $table->string('lock_currency', 8)->default('USD');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'viewer_dm_att_conv_idx');
            $table->index(['owner_user_id', 'lock_price_cents'], 'viewer_dm_att_owner_idx');
        });

        // ── Per-fan unlocks ───────────────────────────────────────────
        Schema::create('viewer_dm_attachment_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained('viewer_dm_attachments')->cascadeOnDelete();
            $table->foreignId('fan_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->string('currency', 8);
            $table->string('gateway', 24)->default('preview');
            $table->string('gateway_charge_id', 80)->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['attachment_id', 'fan_user_id'], 'viewer_dm_att_unlock_unique');
        });

        // ── Mass-message broadcasts ───────────────────────────────────
        Schema::create('dm_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('audience_kind', 24); // followers|subscribers|tier|all_dm_threads
            $table->string('audience_value', 64)->nullable(); // tier id when audience_kind=tier
            $table->text('body');
            $table->string('attachment_url', 1024)->nullable();
            $table->string('attachment_thumb_url', 1024)->nullable();
            $table->string('attachment_kind', 16)->nullable(); // image|gallery|video|audio|voice|file
            $table->unsignedInteger('attachment_lock_price_cents')->default(0);
            $table->string('attachment_lock_currency', 8)->default('USD');
            $table->string('status', 16)->default('draft'); // draft|queued|sending|sent|failed
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at'], 'dm_broadcasts_owner_idx');
        });

        // ── Welcome-message automations ───────────────────────────────
        Schema::create('dm_welcome_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('trigger', 32); // new_follower|new_subscriber
            $table->unsignedBigInteger('tier_id')->nullable(); // when trigger=new_subscriber and a specific tier
            $table->text('body');
            $table->string('attachment_url', 1024)->nullable();
            $table->string('attachment_thumb_url', 1024)->nullable();
            $table->string('attachment_kind', 16)->nullable();
            $table->unsignedInteger('attachment_lock_price_cents')->default(0);
            $table->string('attachment_lock_currency', 8)->default('USD');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'trigger', 'is_active'], 'dm_welcome_rules_owner_idx');
        });

        // ── Users: DM access settings ─────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            // open    — anyone signed in can DM
            // subs    — current paid subscribers only (any tier ≥ dm_min_tier_id)
            // paid    — anyone, but the first message costs dm_pay_price_cents
            // closed  — DMs disabled
            $table->string('dm_access_mode', 12)->default('open')->after('allow_followers');
            $table->unsignedInteger('dm_pay_price_cents')->default(0)->after('dm_access_mode');
            $table->string('dm_pay_currency', 8)->default('USD')->after('dm_pay_price_cents');
            $table->unsignedBigInteger('dm_min_tier_id')->nullable()->after('dm_pay_currency');
            $table->boolean('dm_read_receipts_enabled')->default(true)->after('dm_min_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dm_access_mode', 'dm_pay_price_cents', 'dm_pay_currency',
                'dm_min_tier_id', 'dm_read_receipts_enabled',
            ]);
        });

        Schema::dropIfExists('dm_welcome_rules');
        Schema::dropIfExists('dm_broadcasts');
        Schema::dropIfExists('viewer_dm_attachment_unlocks');
        Schema::dropIfExists('viewer_dm_attachments');

        Schema::table('viewer_dm_messages', function (Blueprint $table) {
            $table->dropIndex('viewer_dm_msg_kind_idx');
            $table->dropColumn(['kind', 'tip_id', 'has_attachments']);
        });

        // Drop the partial unique indexes first (raw SQL because the
        // schema builder has no first-class API for partial indexes).
        DB::statement('DROP INDEX IF EXISTS viewer_dm_link_pair_uniq');
        DB::statement('DROP INDEX IF EXISTS viewer_dm_profile_pair_uniq');

        Schema::table('viewer_dm_conversations', function (Blueprint $table) {
            $table->dropIndex('viewer_dm_creator_idx');
            $table->dropColumn([
                'source', 'creator_user_id', 'paid_to_message',
                'paid_amount_cents', 'paid_currency', 'paid_at',
                'viewer_last_read_at', 'owner_last_read_at',
            ]);
        });

        // Restore the original unique index. Note: this leaves any
        // profile-scoped rows behind (link_id NULL); they'll need to
        // be dropped manually if you really want to roll this back.
        Schema::table('viewer_dm_conversations', function (Blueprint $table) {
            $table->unique(['link_id', 'viewer_user_id'], 'viewer_dm_unique_pair');
        });
    }
};
