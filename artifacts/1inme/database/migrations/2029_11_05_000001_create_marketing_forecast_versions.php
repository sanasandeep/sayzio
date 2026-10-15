<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_forecast_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plan_id')->index();
            $t->foreign('plan_id')->references('id')->on('marketing_plan_calcs')->cascadeOnDelete();
            $t->string('name', 160);
            $t->json('payload');
            $t->boolean('shared')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_forecast_versions');
    }
};
