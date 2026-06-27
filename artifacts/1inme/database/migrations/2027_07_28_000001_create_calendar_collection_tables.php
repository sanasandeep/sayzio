<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Followable Calendar link type (Task #2620).
 *
 * A `calendar` link owns exactly one `calendars` config row — a publishable
 * collection of `calendar_events`. Other users (or OTP-verified viewers)
 * `follow` a calendar via `calendar_follows` (modeled on the creator
 * `follows` table), and their "My Calendar" view aggregates the events of
 * every calendar they follow plus the ones they own. This is distinct from
 * the single-invite `ics` link type and the external-sync `calendar_accounts`
 * infrastructure — it reuses their ICS generation / Google provider code but
 * owns its own tables.
 *
 * Additive + guarded: the project runs against a shared RDS, so every create
 * is wrapped in a hasTable() guard and there are no destructive operations.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('calendars')) {
            Schema::create('calendars', function (Blueprint $t) {
                $t->id();
                // Bridged 1:1 to the owning `links` row (links.type = 'calendar').
                $t->unsignedBigInteger('link_id')->unique();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('title');
                // Friendly per-owner identifier; the public handle is the link alias.
                $t->string('slug', 191)->nullable()->index();
                $t->text('description')->nullable();
                // Default timezone applied to new events when they don't set one.
                $t->string('timezone', 64)->default('UTC');
                $t->string('accent_color', 16)->default('#3d6bff');
                // Whether the calendar is discoverable / followable. Page-level
                // gating still flows through links.visibility like other types.
                $t->boolean('is_public')->default(true);
                $t->unsignedInteger('followers_count')->default(0);
                // Free-form config: default view, hidden filters, etc.
                $t->json('settings')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('calendar_events')) {
            Schema::create('calendar_events', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('calendar_id')->index();
                // Denormalized owner for fast "my owned events" queries.
                $t->unsignedBigInteger('user_id')->index();
                $t->string('title');
                $t->text('description')->nullable();
                // Stored in UTC; `timezone` records the wall-clock zone the
                // owner authored them in so we can render/export correctly.
                $t->dateTime('start_at')->index();
                $t->dateTime('end_at')->nullable();
                $t->string('timezone', 64)->default('UTC');
                $t->boolean('all_day')->default(false);
                // Free-text address + optional map coordinates (Leaflet picker).
                $t->string('location', 512)->nullable();
                $t->decimal('lat', 10, 7)->nullable();
                $t->decimal('lng', 10, 7)->nullable();
                // Lower-cased hashtag strings (no leading #) for filtering.
                $t->json('hashtags')->nullable();
                // External payment / registration link carried on the event.
                $t->string('payment_url', 1024)->nullable();
                // Arbitrary extra key/value params (e.g. price, capacity).
                $t->json('params')->nullable();
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('calendar_follows')) {
            Schema::create('calendar_follows', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('calendar_id')->index();
                // The follower is a User row (dashboard account or the
                // auto-provisioned OTP-verified viewer account).
                $t->unsignedBigInteger('follower_id')->index();
                $t->timestamp('created_at')->nullable();
                $t->unique(['calendar_id', 'follower_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_follows');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendars');
    }
};
