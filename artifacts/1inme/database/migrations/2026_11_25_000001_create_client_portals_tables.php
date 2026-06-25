<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_portals')) {
            Schema::create('client_portals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('vault_client_id')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('name');
                // Branding overrides — fall back to workspace defaults when null.
                $table->string('brand_name')->nullable();
                $table->string('brand_color', 16)->nullable();
                $table->string('brand_logo_url', 1024)->nullable();
                $table->text('welcome_message')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'is_enabled']);
                $table->index(['workspace_id', 'vault_client_id']);
            });
        }

        if (!Schema::hasTable('client_portal_shares')) {
            Schema::create('client_portal_shares', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('portal_id');
                $table->unsignedBigInteger('workspace_id');
                // task_board | cloud_folder | creator_post | invoice | link_performance
                $table->string('shareable_type', 32);
                $table->unsignedBigInteger('shareable_id')->nullable();
                $table->string('label')->nullable();
                $table->json('settings')->nullable();
                $table->unsignedInteger('position')->default(0);
                // Approval workflow (used by drafts).
                $table->boolean('requires_approval')->default(false);
                // null | pending | approved | rejected
                $table->string('approval_status', 16)->nullable();
                $table->timestamp('approval_decided_at')->nullable();
                $table->string('approval_decided_email')->nullable();
                $table->text('approval_comment')->nullable();
                $table->timestamps();

                $table->index(['portal_id', 'shareable_type']);
                $table->index(['workspace_id', 'shareable_type', 'shareable_id']);
            });
        }

        if (!Schema::hasTable('client_portal_links')) {
            Schema::create('client_portal_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('portal_id');
                $table->unsignedBigInteger('workspace_id');
                $table->string('email');
                $table->string('token', 64)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['portal_id', 'email']);
                $table->index('workspace_id');
            });
        }

        if (!Schema::hasTable('client_portal_actions')) {
            Schema::create('client_portal_actions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('portal_id');
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('link_id')->nullable();
                $table->string('email')->nullable();
                // viewed | viewed_section | downloaded | commented | approved | rejected | invoice_pay_clicked
                $table->string('action', 32);
                $table->string('target_type', 32)->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('data')->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('occurred_at')->nullable();

                $table->index(['portal_id', 'occurred_at']);
                $table->index(['workspace_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_actions');
        Schema::dropIfExists('client_portal_links');
        Schema::dropIfExists('client_portal_shares');
        Schema::dropIfExists('client_portals');
    }
};
