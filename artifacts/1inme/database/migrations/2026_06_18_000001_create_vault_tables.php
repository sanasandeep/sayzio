<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('username')->nullable();
            // Sensitive blobs — written/read through WorkspaceEncryption.
            $table->text('password_encrypted')->nullable();
            $table->text('notes_encrypted')->nullable();
            $table->text('custom_fields_encrypted')->nullable();
            // Plaintext metadata kept searchable.
            $table->json('tags')->nullable();
            // 'shared' (workspace-wide via vault.view) | 'private' (creator + owner only).
            $table->string('visibility', 16)->default('shared');
            $table->timestamps();

            $table->index(['workspace_id', 'visibility']);
            $table->index(['workspace_id', 'created_by_user_id']);
        });

        Schema::create('vault_clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('website')->nullable();
            // Convenience denormalised primaries (mirrors first child row) for list/search.
            $table->string('primary_email')->nullable();
            $table->string('primary_phone', 64)->nullable();
            $table->text('notes_encrypted')->nullable();
            $table->text('fields_encrypted')->nullable();
            // Workspace-scoped encrypted JSON list of { network, handle, url }.
            $table->text('social_handles_encrypted')->nullable();
            $table->json('tags')->nullable();
            $table->string('visibility', 16)->default('shared');
            $table->timestamps();

            $table->index(['workspace_id', 'visibility']);
            $table->index(['workspace_id', 'created_by_user_id']);
            $table->index(['workspace_id', 'name']);
        });

        Schema::create('vault_client_emails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('client_id');
            $table->string('email');
            $table->string('label', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index('client_id');
            $table->index('workspace_id');
        });

        Schema::create('vault_client_phones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('client_id');
            $table->string('phone', 64);
            $table->string('label', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->index('client_id');
            $table->index('workspace_id');
        });

        Schema::create('vault_client_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('client_id');
            $table->string('label', 32)->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country', 64)->nullable();
            $table->timestamps();
            $table->index('client_id');
            $table->index('workspace_id');
        });

        Schema::create('vault_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            // Polymorphic parent: 'credential' | 'client'.
            $table->string('parent_type', 16);
            $table->unsignedBigInteger('parent_id');
            $table->string('filename');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->unsignedInteger('size')->default(0);
            $table->string('mime', 128)->nullable();
            // True when the bytes on disk are workspace-encrypted ciphertext.
            // All new uploads set this; the column exists so future imports
            // (or a key-rotation migration) can mark legacy plaintext blobs.
            $table->boolean('encrypted')->default(true);
            $table->timestamps();
            $table->index(['parent_type', 'parent_id']);
            $table->index('workspace_id');
        });

        Schema::create('vault_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            // create | update | delete | reveal | export | view
            $table->string('action', 32);
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_label')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('occurred_at')->nullable();

            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['workspace_id', 'actor_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_audit');
        Schema::dropIfExists('vault_attachments');
        Schema::dropIfExists('vault_client_addresses');
        Schema::dropIfExists('vault_client_phones');
        Schema::dropIfExists('vault_client_emails');
        Schema::dropIfExists('vault_clients');
        Schema::dropIfExists('vault_credentials');
    }
};
