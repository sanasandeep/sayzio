<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_credentials', fn (Blueprint $t) => $t->text('authenticator_encrypted')->nullable());
        Schema::table('delivery_projects', fn (Blueprint $t) => $t->foreignId('vault_client_id')->nullable()->constrained('vault_clients')->nullOnDelete());
        Schema::create('client_tracking_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $t->foreignId('delivery_project_id')->constrained('delivery_projects')->cascadeOnDelete();
            $t->string('kind', 30);
            $t->unsignedBigInteger('resource_id');
            $t->timestamps();
            $t->unique(['delivery_project_id', 'kind', 'resource_id'], 'client_tracking_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_tracking_items');
        Schema::table('delivery_projects', fn (Blueprint $t) => $t->dropConstrainedForeignId('vault_client_id'));
        Schema::table('vault_credentials', fn (Blueprint $t) => $t->dropColumn('authenticator_encrypted'));
    }
};
