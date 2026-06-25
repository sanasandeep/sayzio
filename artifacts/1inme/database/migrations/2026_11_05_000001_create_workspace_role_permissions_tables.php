<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workspace_role_permissions')) {
            Schema::create('workspace_role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->json('matrix');
                $table->timestamps();

                $table->unique('workspace_id');
            });
        }

        if (!Schema::hasTable('workspace_role_permission_audits')) {
            Schema::create('workspace_role_permission_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->json('changes');
                $table->timestamp('created_at')->nullable();

                $table->index(['workspace_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_role_permission_audits');
        Schema::dropIfExists('workspace_role_permissions');
    }
};
