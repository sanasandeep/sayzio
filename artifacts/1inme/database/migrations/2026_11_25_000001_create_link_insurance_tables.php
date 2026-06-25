<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-link "Link Insurance" config + current failover state. We
        // keep the active destination on the link itself so the click
        // handler stays a single read on the already-loaded Link row.
        Schema::table('links', function (Blueprint $table) {
            if (!Schema::hasColumn('links', 'insurance_enabled')) {
                $table->boolean('insurance_enabled')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('links', 'insurance_cadence_minutes')) {
                $table->unsignedSmallInteger('insurance_cadence_minutes')->default(30)->after('insurance_enabled');
            }
            if (!Schema::hasColumn('links', 'insurance_failure_threshold')) {
                $table->unsignedTinyInteger('insurance_failure_threshold')->default(2)->after('insurance_cadence_minutes');
            }
            if (!Schema::hasColumn('links', 'insurance_recovery_threshold')) {
                $table->unsignedTinyInteger('insurance_recovery_threshold')->default(3)->after('insurance_failure_threshold');
            }
            if (!Schema::hasColumn('links', 'insurance_auto_restore')) {
                $table->boolean('insurance_auto_restore')->default(true)->after('insurance_recovery_threshold');
            }
            // 'primary'  = serving long_url (everything healthy)
            // 'failover' = serving a backup because primary is broken
            // 'down'     = primary AND every backup are broken; fallback
            //              message is shown if set, otherwise long_url
            //              (last-resort) is still served.
            if (!Schema::hasColumn('links', 'insurance_state')) {
                $table->string('insurance_state', 16)->default('primary')->after('insurance_auto_restore');
            }
            if (!Schema::hasColumn('links', 'insurance_active_url')) {
                $table->text('insurance_active_url')->nullable()->after('insurance_state');
            }
            if (!Schema::hasColumn('links', 'insurance_consecutive_failures')) {
                $table->unsignedSmallInteger('insurance_consecutive_failures')->default(0)->after('insurance_active_url');
            }
            if (!Schema::hasColumn('links', 'insurance_consecutive_successes')) {
                $table->unsignedSmallInteger('insurance_consecutive_successes')->default(0)->after('insurance_consecutive_failures');
            }
            if (!Schema::hasColumn('links', 'insurance_last_checked_at')) {
                $table->timestamp('insurance_last_checked_at')->nullable()->after('insurance_consecutive_successes');
            }
            if (!Schema::hasColumn('links', 'insurance_last_failover_at')) {
                $table->timestamp('insurance_last_failover_at')->nullable()->after('insurance_last_checked_at');
            }
            if (!Schema::hasColumn('links', 'insurance_fallback_message')) {
                $table->text('insurance_fallback_message')->nullable()->after('insurance_last_failover_at');

                $table->index(['insurance_enabled', 'insurance_last_checked_at'], 'links_insurance_due_idx');
            }
        });

        // Ordered backup destinations. Up to 3 enforced in app code; the
        // schema deliberately doesn't cap so ops can run a one-off script
        // to add more for a special campaign without a migration.
        if (!Schema::hasTable('link_backups')) {
            Schema::create('link_backups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                $table->unsignedTinyInteger('position'); // 1-based, ordered
                $table->text('url');
                $table->string('label', 120)->nullable();
                // Cached health for the dashboard so we don't have to recount
                // through link_health_checks on every page load.
                $table->string('last_status', 16)->nullable(); // healthy|down|unknown
                $table->unsignedSmallInteger('last_http_code')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamps();

                $table->unique(['link_id', 'position']);
                $table->index(['link_id', 'position']);
            });
        }

        // Append-only probe history. Fed to the dashboard's 30-day uptime
        // and to the audit trail of failover decisions.
        if (!Schema::hasTable('link_health_checks')) {
            Schema::create('link_health_checks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();
                // null for primary, otherwise the link_backups.id we probed.
                $table->foreignId('link_backup_id')->nullable()
                    ->constrained('link_backups')->nullOnDelete();
                $table->text('target_url');
                $table->string('status', 16); // healthy|down
                $table->unsignedSmallInteger('http_code')->nullable();
                $table->unsignedInteger('latency_ms')->nullable();
                // dns | tls | connect | http_4xx | http_5xx | timeout |
                // takedown_signal | unknown — null when status=healthy.
                $table->string('error_class', 32)->nullable();
                $table->text('error_detail')->nullable();
                $table->timestamp('checked_at')->index();
                $table->timestamps();

                $table->index(['link_id', 'checked_at']);
                $table->index(['link_id', 'status', 'checked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_health_checks');
        Schema::dropIfExists('link_backups');
        Schema::table('links', function (Blueprint $table) {
            $table->dropIndex('links_insurance_due_idx');
            $table->dropColumn([
                'insurance_enabled',
                'insurance_cadence_minutes',
                'insurance_failure_threshold',
                'insurance_recovery_threshold',
                'insurance_auto_restore',
                'insurance_state',
                'insurance_active_url',
                'insurance_consecutive_failures',
                'insurance_consecutive_successes',
                'insurance_last_checked_at',
                'insurance_last_failover_at',
                'insurance_fallback_message',
            ]);
        });
    }
};
