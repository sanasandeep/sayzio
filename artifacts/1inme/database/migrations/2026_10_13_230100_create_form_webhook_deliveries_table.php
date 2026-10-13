<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $t->unsignedInteger('hook_index');
            $t->boolean('is_test')->default(false);
            $t->unsignedSmallInteger('status_code')->nullable();
            $t->boolean('success')->default(false);
            $t->string('message', 200);
            $t->timestamp('created_at');
            $t->index(['form_id', 'created_at']);
        });
        Schema::table('form_visits', fn (Blueprint $t) => $t->unsignedBigInteger('submission_id')->nullable()->index());
    }

    public function down(): void
    {
        Schema::dropIfExists('form_webhook_deliveries');
        Schema::table('form_visits', fn (Blueprint $t) => $t->dropColumn('submission_id'));
    }
};
