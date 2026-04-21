<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_provider_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            // 'google_drive' | 'dropbox' | 'onedrive'
            $table->string('provider', 32);
            $table->string('client_id')->nullable();
            // Encrypted via Laravel Crypt cast on the model.
            $table->text('client_secret_encrypted')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });

        Schema::create('cloud_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 32);
            $table->string('account_label')->nullable();
            $table->string('account_email')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'user_id', 'provider']);
        });

        Schema::create('cloud_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('added_by_user_id');
            $table->unsignedBigInteger('connection_id');
            $table->string('provider', 32);
            $table->string('remote_id');
            $table->string('name');
            $table->string('mime', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('link', 1024);
            $table->string('thumbnail_url', 1024)->nullable();
            $table->string('parent_folder_path')->nullable();
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'provider', 'remote_id']);
            $table->index(['workspace_id', 'provider']);
            $table->index(['workspace_id', 'added_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_files');
        Schema::dropIfExists('cloud_connections');
        Schema::dropIfExists('cloud_provider_apps');
    }
};
