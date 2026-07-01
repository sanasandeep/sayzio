<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connected Apps — per-creator connections to external CRMs (Salesforce,
 * HubSpot, Zoho) and analytics (Google Analytics 4). One row per
 * (user, provider). CRMs authenticate via OAuth (admin-provided client
 * credentials); GA connects with a Measurement ID + API secret supplied by
 * the creator. Tokens/secrets are stored in the `encrypted` cast columns.
 *
 * Idempotent + additive per the repo convention (guarded create/hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('connected_apps')) {
            Schema::create('connected_apps', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();

                // salesforce | hubspot | zoho | google_analytics
                $table->string('provider', 40);
                // crm | analytics
                $table->string('kind', 20)->default('crm');
                // connected | error | paused
                $table->string('status', 20)->default('connected');

                // OAuth / credential material (encrypted casts on the model).
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();

                // Per-provider endpoints & identity.
                $table->string('instance_url')->nullable();      // Salesforce/Zoho API domain
                $table->string('external_account_id')->nullable();
                $table->string('account_label')->nullable();
                $table->string('account_email')->nullable();
                $table->text('scope')->nullable();

                // Two-way sync config + state.
                $table->json('field_mappings')->nullable();
                $table->json('settings')->nullable();            // push/pull toggles, GA measurement_id, etc.
                $table->text('pull_cursor')->nullable();
                $table->boolean('pull_enabled')->default(true);
                $table->boolean('push_enabled')->default(true);

                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('last_pull_at')->nullable();
                $table->string('last_sync_status', 20)->nullable();
                $table->text('last_sync_error')->nullable();
                $table->unsignedInteger('records_sent')->default(0);
                $table->unsignedInteger('records_pulled')->default(0);

                $table->timestamp('paused_at')->nullable();
                $table->timestamp('connected_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
                $table->index(['provider', 'kind']);
            });

            return;
        }

        // Table already exists (partial/older env) — converge additively.
        Schema::table('connected_apps', function (Blueprint $table) {
            foreach ([
                'kind', 'status', 'access_token', 'refresh_token', 'token_expires_at',
                'instance_url', 'external_account_id', 'account_label', 'account_email',
                'scope', 'field_mappings', 'settings', 'pull_cursor', 'pull_enabled',
                'push_enabled', 'last_synced_at', 'last_pull_at', 'last_sync_status',
                'last_sync_error', 'records_sent', 'records_pulled', 'paused_at', 'connected_at',
            ] as $col) {
                if (Schema::hasColumn('connected_apps', $col)) {
                    continue;
                }
                match ($col) {
                    'kind'                => $table->string('kind', 20)->default('crm'),
                    'status'              => $table->string('status', 20)->default('connected'),
                    'access_token', 'refresh_token', 'scope', 'pull_cursor', 'last_sync_error'
                                          => $table->text($col)->nullable(),
                    'token_expires_at', 'last_synced_at', 'last_pull_at', 'paused_at', 'connected_at'
                                          => $table->timestamp($col)->nullable(),
                    'instance_url', 'external_account_id', 'account_label', 'account_email'
                                          => $table->string($col)->nullable(),
                    'last_sync_status'    => $table->string($col, 20)->nullable(),
                    'field_mappings', 'settings' => $table->json($col)->nullable(),
                    'pull_enabled', 'push_enabled' => $table->boolean($col)->default(true),
                    'records_sent', 'records_pulled' => $table->unsignedInteger($col)->default(0),
                    default               => null,
                };
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_apps');
    }
};
