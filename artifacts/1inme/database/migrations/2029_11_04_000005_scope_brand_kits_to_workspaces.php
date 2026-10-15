<?php

use App\Modules\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', fn (Blueprint $table) => $table->foreignId('workspace_id')->nullable()->index()->constrained('workspaces')->nullOnDelete());
        DB::table('brand_kits')->orderBy('id')->chunkById(200, function ($kits) {
            foreach ($kits as $kit) {
                $id = DB::table('workspaces')->where('owner_user_id', $kit->user_id)->where('is_personal', true)->value('id');
                if (! $id) {
                    $id = User::find($kit->user_id)?->ensureDefaultWorkspace()->id;
                }if ($id) {
                    DB::table('brand_kits')->where('id', $kit->id)->update(['workspace_id' => $id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_id'));
    }
};
