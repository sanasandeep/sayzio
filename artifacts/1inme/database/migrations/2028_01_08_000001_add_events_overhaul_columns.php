<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3593 (Events overhaul): hashtags, richer pages (cover image /
 * gallery / info sections), badge-powered invite/entry rules, and the
 * one-tap Interested/Not-interested signal (separate from RSVP).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ics_data', 'hashtags')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->json('hashtags')->nullable()->after('extra_schedules');
            });
        }
        if (!Schema::hasColumn('ics_data', 'gallery')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->json('gallery')->nullable()->after('hashtags');
            });
        }
        if (!Schema::hasColumn('ics_data', 'info_sections')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->json('info_sections')->nullable()->after('gallery');
            });
        }
        if (!Schema::hasColumn('ics_data', 'cover_image_url')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->string('cover_image_url', 2048)->nullable()->after('info_sections');
            });
        }
        if (!Schema::hasColumn('ics_data', 'required_badge_id')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->foreignId('required_badge_id')->nullable()->after('cover_image_url')
                    ->constrained('account_badges')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('ics_data', 'award_badge_id')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->foreignId('award_badge_id')->nullable()->after('required_badge_id')
                    ->constrained('account_badges')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('event_interests')) {
            Schema::create('event_interests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('guest_email', 255)->nullable();
                $table->string('guest_fingerprint', 64)->nullable();
                $table->string('status', 20); // interested | not_interested
                $table->timestamps();

                $table->index(['link_id', 'status']);
            });

            // Partial unique indexes (Postgres) so a signed-in user or a
            // fingerprinted guest can only hold one active signal per event.
            DB::statement('CREATE UNIQUE INDEX event_interests_user_unique ON event_interests (link_id, user_id) WHERE user_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX event_interests_guest_unique ON event_interests (link_id, guest_fingerprint) WHERE user_id IS NULL AND guest_fingerprint IS NOT NULL');
        }

        if (!Schema::hasColumn('users', 'event_alerts_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('event_alerts_enabled')->default(false)->after('adult_content_enabled_at');
                $table->decimal('event_alert_latitude', 10, 7)->nullable()->after('event_alerts_enabled');
                $table->decimal('event_alert_longitude', 10, 7)->nullable()->after('event_alert_latitude');
                $table->unsignedSmallInteger('event_alert_radius_km')->default(25)->after('event_alert_longitude');
                $table->string('event_alert_frequency', 20)->default('instant')->after('event_alert_radius_km'); // instant | daily_digest
            });
        }

        if (!Schema::hasTable('event_new_alerts_sent')) {
            Schema::create('event_new_alerts_sent', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('sent_at')->useCurrent();
                $table->unique(['link_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_new_alerts_sent');

        if (Schema::hasColumn('users', 'event_alerts_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'event_alerts_enabled', 'event_alert_latitude', 'event_alert_longitude',
                    'event_alert_radius_km', 'event_alert_frequency',
                ]);
            });
        }

        Schema::dropIfExists('event_interests');

        if (Schema::hasColumn('ics_data', 'award_badge_id')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->dropConstrainedForeignId('award_badge_id');
            });
        }
        if (Schema::hasColumn('ics_data', 'required_badge_id')) {
            Schema::table('ics_data', function (Blueprint $table) {
                $table->dropConstrainedForeignId('required_badge_id');
            });
        }
        Schema::table('ics_data', function (Blueprint $table) {
            $cols = array_filter(['cover_image_url', 'info_sections', 'gallery', 'hashtags'],
                fn ($c) => Schema::hasColumn('ics_data', $c));
            if ($cols) $table->dropColumn($cols);
        });
    }
};
