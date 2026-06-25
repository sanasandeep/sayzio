<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('google_contacts_accounts')) {
            Schema::create('google_contacts_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('account_email', 191)->nullable();
                $table->string('external_account_id', 191)->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestampTz('token_expires_at')->nullable();
                $table->string('scope', 1024)->nullable();
                $table->string('sync_token', 2048)->nullable();
                $table->timestampTz('last_synced_at')->nullable();
                $table->string('last_sync_status', 32)->nullable();
                $table->text('last_sync_error')->nullable();
                $table->boolean('pull_enabled')->default(true);
                $table->boolean('push_enabled')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->index('user_id');
            });
        }

        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('google_contacts_account_id')->nullable()->constrained()->nullOnDelete();
                $table->string('google_resource_name', 191)->nullable();
                $table->string('google_etag', 191)->nullable();
                $table->string('display_name', 191)->nullable();
                $table->string('given_name', 191)->nullable();
                $table->string('family_name', 191)->nullable();
                $table->string('organization', 191)->nullable();
                $table->string('job_title', 191)->nullable();
                $table->text('notes')->nullable();
                $table->string('photo_path', 500)->nullable();
                $table->string('photo_url', 500)->nullable();
                $table->foreignId('biolink_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('biolink_attached_at')->nullable();
                $table->json('detached_biolink_user_ids')->nullable();
                $table->timestampTz('last_synced_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'display_name']);
                $table->index(['user_id', 'biolink_user_id']);
                $table->unique(['user_id', 'google_resource_name']);
            });
        }

        if (!Schema::hasTable('contact_phones')) {
            Schema::create('contact_phones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
                $table->string('label', 50)->nullable();
                $table->string('value', 80);
                $table->string('value_e164', 32)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index(['contact_id']);
                $table->index(['value_e164']);
            });
        }

        if (!Schema::hasTable('contact_emails')) {
            Schema::create('contact_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
                $table->string('label', 50)->nullable();
                $table->string('value', 191);
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
                $table->index(['contact_id']);
                $table->index(['value']);
            });
        }

        if (!Schema::hasTable('dialer_lookups')) {
            Schema::create('dialer_lookups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('number_e164', 32);
                $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
                $table->timestampTz('looked_up_at')->useCurrent();
                $table->index(['user_id', 'looked_up_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dialer_lookups');
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contact_phones');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('google_contacts_accounts');
    }
};
