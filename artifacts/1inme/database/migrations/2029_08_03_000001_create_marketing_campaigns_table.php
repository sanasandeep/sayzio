<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('name', 160);
            $table->string('path', 20)->default('quick');
            $table->json('brief')->nullable();
            $table->json('history')->nullable();
            $table->json('strategy_ids')->nullable();
            $table->json('studio_ids')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id', 'plan_id'], 'campaign_plan_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
