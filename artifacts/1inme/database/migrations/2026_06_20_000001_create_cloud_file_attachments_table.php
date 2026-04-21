<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloud_file_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('cloud_file_id');
            $table->string('attachable_type', 191);
            $table->unsignedBigInteger('attachable_id');
            $table->unsignedBigInteger('attached_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['cloud_file_id', 'attachable_type', 'attachable_id'], 'cfa_unique');
            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['workspace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_file_attachments');
    }
};
