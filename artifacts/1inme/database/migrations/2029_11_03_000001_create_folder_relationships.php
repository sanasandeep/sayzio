<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_relationships', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('source_kind', 30);
            $t->unsignedBigInteger('source_id');
            $t->string('target_kind', 30);
            $t->unsignedBigInteger('target_id');
            $t->string('label', 120)->default('Related to');
            $t->timestamps();
            $t->unique(['project_id', 'source_kind', 'source_id', 'target_kind', 'target_id'], 'folder_relationship_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_relationships');
    }
};
