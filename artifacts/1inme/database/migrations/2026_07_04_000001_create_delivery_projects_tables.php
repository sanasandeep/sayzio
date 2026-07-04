<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3564 — "Delivery Projects": turn a finalized sale (client invoice,
 * product order, restaurant/store order, paid form) into a lightweight shared
 * project with a task list + Gantt timeline. Deliberately NOT reusing the
 * existing `projects` table (that is the unrelated link-organisation folder
 * feature) — this is a separate, sale-anchored concept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_projects')) {
            Schema::create('delivery_projects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();

                // Polymorphic link back to the sale this project was spun up from.
                $table->string('sourceable_type')->nullable();
                $table->unsignedBigInteger('sourceable_id')->nullable();

                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status', 20)->default('active'); // active | completed | archived

                // Buyer/client the project is shared with. client_user_id is set
                // when the buyer is a registered user; name/email cover anonymous
                // buyers (restaurant/store orders) surfaced via share_token.
                $table->foreignId('client_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('client_name')->nullable();
                $table->string('client_email')->nullable();
                $table->string('share_token', 64)->nullable()->unique();

                $table->timestamp('completed_at')->nullable();

                // Warranty + reminder (user request): after completion, a warranty
                // window can be set; the creator is reminded N days before and once
                // on/after expiry. Stamps below make the sweep idempotent.
                $table->date('warranty_expires_at')->nullable();
                $table->unsignedSmallInteger('warranty_reminder_days')->nullable();
                $table->timestamp('warranty_reminder_sent_at')->nullable();
                $table->timestamp('warranty_expired_notified_at')->nullable();

                $table->timestamps();

                $table->index(['workspace_id', 'status']);
                $table->index(['sourceable_type', 'sourceable_id']);
                $table->index('warranty_expires_at');
            });
        }

        if (!Schema::hasTable('delivery_project_tasks')) {
            Schema::create('delivery_project_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('delivery_projects')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('title');
                $table->string('status', 20)->default('todo'); // todo | in_progress | done
                $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->unsignedTinyInteger('progress')->default(0); // 0-100
                $table->integer('position')->default(0);
                $table->timestamps();

                $table->index(['project_id', 'position']);
                $table->index(['workspace_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_tasks');
        Schema::dropIfExists('delivery_projects');
    }
};
